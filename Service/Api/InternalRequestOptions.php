<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Api;

use Magento\Framework\Serialize\Serializer\Json;
use MaggyAssistant\Base\Api\Config\RepositoryInterface as ConfigRepositoryInterface;

final class InternalRequestOptions
{
    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';
    public const METHOD_PUT = 'PUT';
    public const METHOD_DELETE = 'DELETE';

    private const TIMEOUT_SECONDS = 30;
    private const VERIFY_HOST_STRICT = 2;
    private const VERIFY_HOST_OFF = 0;
    private const FALLBACK_STORE_HOST = 'localhost';

    public function __construct(
        private readonly ConfigRepositoryInterface $configRepository,
        private readonly Json $json
    ) {
    }

    /**
     * @return array<int, mixed> Options ready for curl_setopt_array
     */
    public function build(string $method, string $url, ?array $body, string $token, string $storeBaseUrl): array
    {
        return $this->baseOptions($url, $token, $storeBaseUrl) + $this->methodOptions($method, $body);
    }

    /**
     * @return array<int, mixed>
     */
    private function baseOptions(string $url, string $token, string $storeBaseUrl): array
    {
        $isSslVerifyEnabled = $this->configRepository->isInternalSslVerifyEnabled();

        return [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->headers($url, $token, $storeBaseUrl),
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => $isSslVerifyEnabled,
            CURLOPT_SSL_VERIFYHOST => $isSslVerifyEnabled ? self::VERIFY_HOST_STRICT : self::VERIFY_HOST_OFF,
        ];
    }

    /**
     * @return string[]
     */
    private function headers(string $url, string $token, string $storeBaseUrl): array
    {
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $storeHost = $this->storeHost($storeBaseUrl);
        if (parse_url($url, PHP_URL_HOST) === $storeHost) {
            return $headers;
        }

        return [...$headers, 'Host: ' . $storeHost];
    }

    private function storeHost(string $storeBaseUrl): string
    {
        return parse_url($storeBaseUrl, PHP_URL_HOST) ?: self::FALLBACK_STORE_HOST;
    }

    /**
     * @return array<int, mixed>
     */
    private function methodOptions(string $method, ?array $body): array
    {
        return match ($method) {
            self::METHOD_POST => [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $this->json->serialize($body ?? []),
            ],
            self::METHOD_PUT => [
                CURLOPT_CUSTOMREQUEST => self::METHOD_PUT,
                CURLOPT_POSTFIELDS => $this->json->serialize($body ?? []),
            ],
            self::METHOD_DELETE => [CURLOPT_CUSTOMREQUEST => self::METHOD_DELETE],
            default => [],
        };
    }
}
