<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Skills\Configuration;

use Magento\Framework\App\Config\ScopeConfigInterface;
use MaggyAssistant\Base\Api\Tool\ToolInterface;

class ConfigReader implements ToolInterface
{
    private const BLOCKED_PATTERNS = [
        '*key*', '*secret*', '*password*', '*token*', '*credential*',
        'payment/*', '*api_key*', '*private*', '*encrypt*',
    ];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getName(): string
    {
        return 'config_reader';
    }

    public function getDescription(): string
    {
        return 'Read Magento store configuration values. Provide the config path (e.g. "general/store_information/name", "web/secure/base_url"). Sensitive paths containing keys, secrets, passwords, tokens or payment config are blocked.';
    }

    public function getParameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => [
                    'type' => 'string',
                    'description' => 'The configuration path to read (e.g. "general/store_information/name")',
                ],
                'scope' => [
                    'type' => 'string',
                    'description' => 'Scope: "default", "websites", or "stores"',
                    'enum' => ['default', 'websites', 'stores'],
                ],
                'scope_id' => [
                    'type' => 'integer',
                    'description' => 'Scope ID (store or website ID). 0 for default.',
                ],
            ],
            'required' => ['path'],
        ];
    }

    public function execute(array $params): array
    {
        $path = $params['path'] ?? '';
        if (!$path) {
            return ['error' => 'Path parameter is required'];
        }

        if ($this->isBlockedPath($path)) {
            return ['error' => 'Access to this configuration path is restricted for security reasons'];
        }

        $scope = $params['scope'] ?? 'default';
        $scopeId = $params['scope_id'] ?? 0;

        $value = $this->scopeConfig->getValue($path, $scope, $scopeId);

        return [
            'path' => $path,
            'value' => $value,
            'scope' => $scope,
            'scope_id' => $scopeId,
        ];
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function isReadOnlyAction(array $input): bool
    {
        return $this->isReadOnly();
    }

    public function getInstructions(): string
    {
        return '';
    }

    public function getMagentoAcl(array $input = []): string
    {
        return 'Magento_Config::config';
    }

    private function isBlockedPath(string $path): bool
    {
        $pathLower = strtolower($path);
        foreach (self::BLOCKED_PATTERNS as $pattern) {
            $regex = '/^' . str_replace(['*', '/'], ['.*', '\/'], $pattern) . '$/';
            if (preg_match($regex, $pathLower)) {
                return true;
            }
        }
        // Also check path segments
        $segments = explode('/', $pathLower);
        $blockedWords = ['key', 'secret', 'password', 'token', 'credential', 'private', 'encrypt'];
        foreach ($segments as $segment) {
            foreach ($blockedWords as $word) {
                if (str_contains($segment, $word)) {
                    return true;
                }
            }
        }
        if (str_starts_with($pathLower, 'payment/')) {
            return true;
        }
        return false;
    }
}
