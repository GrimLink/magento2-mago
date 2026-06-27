<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Skills\Configuration;

use Magento\Config\Model\ResourceModel\Config as ConfigResource;
use Magento\Framework\App\Cache\TypeListInterface;
use MaggyAssistant\Base\Api\Tool\ToolInterface;

class ConfigWriter implements ToolInterface
{
    private const BLOCKED_PATTERNS = [
        '*key*', '*secret*', '*password*', '*token*', '*credential*',
        'payment/*', '*api_key*', '*private*', '*encrypt*',
    ];

    public function __construct(
        private readonly ConfigResource $configResource,
        private readonly TypeListInterface $cacheTypeList
    ) {
    }

    public function getName(): string
    {
        return 'config_writer';
    }

    public function getDescription(): string
    {
        return 'Modify Magento store configuration values. Requires merchant confirmation before execution. Sensitive paths are blocked.';
    }

    public function getParameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => [
                    'type' => 'string',
                    'description' => 'The configuration path to set (e.g. "general/store_information/name")',
                ],
                'value' => [
                    'type' => 'string',
                    'description' => 'The value to set',
                ],
                'scope' => [
                    'type' => 'string',
                    'description' => 'Scope: "default", "websites", or "stores"',
                    'enum' => ['default', 'websites', 'stores'],
                ],
                'scope_id' => [
                    'type' => 'integer',
                    'description' => 'Scope ID (0 for default)',
                ],
            ],
            'required' => ['path', 'value'],
        ];
    }

    public function execute(array $params): array
    {
        $path = $params['path'] ?? '';
        $value = $params['value'] ?? '';

        if (!$path) {
            return ['error' => 'Path parameter is required'];
        }

        if ($this->isBlockedPath($path)) {
            return ['error' => 'Cannot modify this configuration path for security reasons'];
        }

        $scope = $params['scope'] ?? 'default';
        $scopeId = (int)($params['scope_id'] ?? 0);

        $this->configResource->saveConfig($path, $value, $scope, $scopeId);
        $this->cacheTypeList->cleanType('config');

        return [
            'success' => true,
            'path' => $path,
            'value' => $value,
            'scope' => $scope,
            'scope_id' => $scopeId,
            'message' => sprintf('Configuration "%s" has been set to "%s"', $path, $value),
        ];
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function isReadOnlyAction(array $input): bool
    {
        return $this->isReadOnly();
    }

    public function getRequiredAcl(): string
    {
        return 'MaggyAssistant_Base::assistant_write';
    }

    public function getInstructions(): string
    {
        return '';
    }

    public function getMagentoAcl(): string
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
