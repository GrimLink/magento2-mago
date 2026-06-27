<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Model\Ai\Provider;

use MaggyAssistant\Base\Api\Ai\ProviderInterface;
use MaggyAssistant\Base\Api\Config\RepositoryInterface as ConfigRepository;
use MaggyAssistant\Base\Service\Ai\RestClient;

class OpenAi implements ProviderInterface
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly RestClient $restClient
    ) {
    }

    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        $body = $this->buildRequestBody($messages, $tools, $options);
        $headers = $this->getHeaders();

        $response = $this->restClient->execute(
            self::API_URL,
            $headers,
            $body,
            $this->configRepository->isDebugEnabled()
        );

        if (!empty($response['error'])) {
            return [
                'content' => 'Error: ' . ($response['message'] ?? 'Unknown error'),
                'tool_calls' => [],
            ];
        }

        $parsed = $this->parseResponse($response);
        $parsed['usage'] = [
            'input_tokens' => $response['usage']['prompt_tokens'] ?? 0,
            'output_tokens' => $response['usage']['completion_tokens'] ?? 0,
        ];
        return $parsed;
    }

    public function stream(array $messages, array $tools = [], array $options = [], ?callable $onChunk = null): array
    {
        $body = $this->buildRequestBody($messages, $tools, $options);
        $body['stream'] = true;
        $body['stream_options'] = ['include_usage' => true];
        $headers = $this->getHeaders();

        $fullContent = '';
        $toolCalls = [];
        $currentToolCalls = [];
        $buffer = '';
        $usage = ['input_tokens' => 0, 'output_tokens' => 0];

        $this->restClient->stream(
            self::API_URL,
            $headers,
            $body,
            function (string $chunk) use (&$fullContent, &$toolCalls, &$currentToolCalls, &$buffer, &$usage, $onChunk) {
                $buffer .= $chunk;
                $lines = explode("\n", $buffer);
                $buffer = array_pop($lines);

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!str_starts_with($line, 'data: ')) {
                        continue;
                    }

                    $data = substr($line, 6);
                    if ($data === '[DONE]') {
                        continue;
                    }

                    try {
                        $event = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                    } catch (\Throwable $e) {
                        continue;
                    }

                    if (!empty($event['usage'])) {
                        $usage['input_tokens'] = $event['usage']['prompt_tokens'] ?? 0;
                        $usage['output_tokens'] = $event['usage']['completion_tokens'] ?? 0;
                    }

                    $delta = $event['choices'][0]['delta'] ?? [];

                    if (!empty($delta['content'])) {
                        $fullContent .= $delta['content'];
                        if ($onChunk) {
                            $onChunk('text', ['text' => $delta['content']]);
                        }
                    }

                    if (!empty($delta['tool_calls'])) {
                        foreach ($delta['tool_calls'] as $tc) {
                            $index = $tc['index'] ?? 0;
                            if (!isset($currentToolCalls[$index])) {
                                $currentToolCalls[$index] = [
                                    'id' => $tc['id'] ?? '',
                                    'name' => $tc['function']['name'] ?? '',
                                    'arguments' => '',
                                ];
                                // Send tool_call event immediately when we first see the tool name
                                if ($onChunk && !empty($tc['function']['name'])) {
                                    $onChunk('tool_call', [
                                        'id' => $tc['id'] ?? '',
                                        'name' => $tc['function']['name'],
                                    ]);
                                }
                            }
                            if (!empty($tc['function']['arguments'])) {
                                $currentToolCalls[$index]['arguments'] .= $tc['function']['arguments'];
                            }
                        }
                    }
                }
            },
            $this->configRepository->isDebugEnabled()
        );

        // Build final tool calls with parsed arguments
        foreach ($currentToolCalls as $tc) {
            $input = [];
            if (!empty($tc['arguments'])) {
                try {
                    $input = json_decode($tc['arguments'], true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable $e) {
                    $input = [];
                }
            }
            $toolCalls[] = [
                'id' => $tc['id'],
                'name' => $tc['name'],
                'input' => $input,
            ];
        }

        return [
            'content' => $fullContent,
            'tool_calls' => $toolCalls,
            'usage' => $usage,
        ];
    }

    public function getProviderName(): string
    {
        return 'openai';
    }

    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->configRepository->getApiKey(),
        ];
    }

    private function buildRequestBody(array $messages, array $tools, array $options): array
    {
        $body = [
            'model' => $options['model'] ?? $this->configRepository->getModel(),
            'max_tokens' => $options['max_tokens'] ?? $this->configRepository->getMaxTokens(),
            'messages' => $this->formatMessages($messages),
        ];

        $temperature = $options['temperature'] ?? $this->configRepository->getTemperature();
        if ($temperature > 0) {
            $body['temperature'] = $temperature;
        }

        if (!empty($tools)) {
            $body['tools'] = $this->formatTools($tools);
        }

        return $body;
    }

    private function formatMessages(array $messages): array
    {
        $formatted = [];
        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            if ($role === 'tool') {
                $formatted[] = [
                    'role' => 'tool',
                    'tool_call_id' => $message['tool_call_id'] ?? '',
                    'content' => $message['content'] ?? '',
                ];
            } elseif ($role === 'assistant' && !empty($message['tool_calls'])) {
                $openAiToolCalls = [];
                foreach ($message['tool_calls'] as $tc) {
                    $openAiToolCalls[] = [
                        'id' => $tc['id'],
                        'type' => 'function',
                        'function' => [
                            'name' => $tc['name'],
                            'arguments' => json_encode($tc['input'] ?? []),
                        ],
                    ];
                }
                $formatted[] = [
                    'role' => 'assistant',
                    'content' => $message['content'] ?? null,
                    'tool_calls' => $openAiToolCalls,
                ];
            } else {
                $formatted[] = [
                    'role' => $role,
                    'content' => $message['content'] ?? '',
                ];
            }
        }
        return $formatted;
    }

    private function formatTools(array $tools): array
    {
        $formatted = [];
        foreach ($tools as $tool) {
            $formatted[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parameters' => $tool['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ];
        }
        return $formatted;
    }

    private function parseResponse(array $response): array
    {
        $choice = $response['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        $content = $message['content'] ?? '';
        $toolCalls = [];

        foreach (($message['tool_calls'] ?? []) as $tc) {
            $input = [];
            $args = $tc['function']['arguments'] ?? '';
            if ($args) {
                try {
                    $input = json_decode($args, true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable $e) {
                    $input = [];
                }
            }
            $toolCalls[] = [
                'id' => $tc['id'] ?? '',
                'name' => $tc['function']['name'] ?? '',
                'input' => $input,
            ];
        }

        return [
            'content' => $content,
            'tool_calls' => $toolCalls,
        ];
    }
}
