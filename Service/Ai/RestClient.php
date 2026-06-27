<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Ai;

use Laminas\Http\Client\Adapter\Curl;
use Laminas\Http\ClientFactory;
use Laminas\Http\HeadersFactory;
use Laminas\Http\Request;
use Magento\Framework\Serialize\Serializer\Json;
use MaggyAssistant\Base\Logger\DebugLogger;
use MaggyAssistant\Base\Logger\ErrorLogger;

class RestClient
{
    public function __construct(
        private readonly Json $json,
        private readonly HeadersFactory $headersFactory,
        private readonly ClientFactory $clientFactory,
        private readonly DebugLogger $debugLogger,
        private readonly ErrorLogger $errorLogger
    ) {
    }

    /**
     * Execute a non-streaming API call
     *
     * @param string $url
     * @param array $headers
     * @param array $body
     * @param bool $debug
     * @return array
     */
    public function execute(string $url, array $headers, array $body, bool $debug = false): array
    {
        if ($debug) {
            $this->debugLogger->addLog('API Request', ['url' => $url, 'body' => $body]);
        }

        $httpHeaders = $this->headersFactory->create();
        $httpHeaders->addHeaders(array_merge(['Content-Type' => 'application/json'], $headers));

        try {
            $client = $this->clientFactory->create();
            $client->setMethod(Request::METHOD_POST);
            $client->setUri($url);
            $client->setHeaders($httpHeaders);
            $client->setRawBody($this->json->serialize($body));
            $client->setEncType('application/json');
            $client->setOptions([
                'adapter' => Curl::class,
                'curloptions' => [CURLOPT_FOLLOWLOCATION => true],
                'maxredirects' => 0,
                'timeout' => 120,
            ]);

            $response = $client->send();
            $responseBody = $response->getBody();
            $statusCode = $response->getStatusCode();

            if ($debug) {
                $this->debugLogger->addLog('API Response', ['status' => $statusCode, 'body' => $responseBody]);
            }

            if ($statusCode >= 200 && $statusCode < 300) {
                return $this->json->unserialize($responseBody);
            }

            $this->errorLogger->addLog('API Error', [
                'status' => $statusCode,
                'body' => $responseBody,
                'url' => $url,
            ]);

            return ['error' => true, 'status' => $statusCode, 'message' => $responseBody];
        } catch (\Throwable $e) {
            $this->errorLogger->addLog('API Exception', $e->getMessage());
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Execute a streaming API call using raw curl
     *
     * @param string $url
     * @param array $headers
     * @param array $body
     * @param callable $onChunk fn(string $chunk)
     * @param bool $debug
     * @return void
     */
    public function stream(string $url, array $headers, array $body, callable $onChunk, bool $debug = false): void
    {
        if ($debug) {
            $this->debugLogger->addLog('Stream Request', ['url' => $url, 'body' => $body]);
        }

        $curlHeaders = ['Content-Type: application/json'];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = "$key: $value";
        }

        $responseBuffer = '';
        $httpCode = 0;
        $headersDone = false;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $this->json->serialize($body),
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($onChunk, $debug, &$responseBuffer, &$httpCode, &$headersDone) {
                if (!$headersDone) {
                    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $headersDone = true;
                }
                if ($httpCode >= 400) {
                    $responseBuffer .= $data;
                } else {
                    $onChunk($data);
                }
                return strlen($data);
            },
        ]);

        $result = curl_exec($ch);
        if ($result === false) {
            $error = curl_error($ch);
            $this->errorLogger->addLog('Stream Error', $error);
            throw new \RuntimeException('Connection error: ' . $error);
        }

        if ($httpCode >= 400) {
            $this->errorLogger->addLog('Stream API Error', [
                'status' => $httpCode,
                'body' => $responseBuffer,
            ]);
            $errorMsg = 'API error (HTTP ' . $httpCode . ')';
            try {
                $decoded = json_decode($responseBuffer, true, 512, JSON_THROW_ON_ERROR);
                $errorMsg = $decoded['error']['message']
                    ?? $decoded['message']
                    ?? $errorMsg;
            } catch (\Throwable $e) {
                // use generic message
            }
            throw new \RuntimeException($errorMsg);
        }

        unset($ch);
    }
}
