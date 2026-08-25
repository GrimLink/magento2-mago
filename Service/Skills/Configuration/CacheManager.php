<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Skills\Configuration;

use Magento\Framework\App\Cache\Frontend\Pool as CacheFrontendPool;
use Magento\Framework\App\Cache\TypeListInterface;
use MaggyAssistant\Base\Api\Tool\ToolInterface;

class CacheManager implements ToolInterface
{
    public function __construct(
        private readonly TypeListInterface $cacheTypeList,
        private readonly CacheFrontendPool $cacheFrontendPool
    ) {
    }

    public function getName(): string
    {
        return 'cache_manager';
    }

    public function getDescription(): string
    {
        return 'Manage Magento caches. Actions: "status" (list all cache types and their status), '
            . '"flush" (flush all caches), "flush_type" (flush a specific cache type by id, e.g. "config", "full_page", "layout", "block_html").';
    }

    public function getParameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'description' => 'The action to perform',
                    'enum' => ['status', 'flush', 'flush_type'],
                ],
                'cache_type' => [
                    'type' => 'string',
                    'description' => 'Cache type ID for flush_type action (e.g. "config", "full_page", "layout", "block_html", "collections", "reflection", "eav", "translate")',
                ],
            ],
            'required' => ['action'],
        ];
    }

    public function execute(array $params): array
    {
        $action = $params['action'] ?? '';

        return match ($action) {
            'status' => $this->getStatus(),
            'flush' => $this->flushAll(),
            'flush_type' => $this->flushType($params['cache_type'] ?? ''),
            default => ['error' => 'Unknown action: ' . $action],
        };
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function isReadOnlyAction(array $input): bool
    {
        // status only reads cache state; flush/flush_type mutate. Unknown actions fail closed to write.
        return ($input['action'] ?? '') === 'status';
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
        return 'Magento_Backend::cache';
    }

    private function getStatus(): array
    {
        $types = $this->cacheTypeList->getTypes();
        $result = [];
        foreach ($types as $type) {
            $result[] = [
                'id' => $type->getId(),
                'label' => (string)$type->getCacheType(),
                'status' => $type->getStatus() ? 'enabled' : 'disabled',
            ];
        }
        return ['cache_types' => $result];
    }

    private function flushAll(): array
    {
        $types = $this->cacheTypeList->getTypes();
        $flushed = [];
        foreach ($types as $type) {
            $this->cacheTypeList->cleanType($type->getId());
            $flushed[] = $type->getId();
        }
        foreach ($this->cacheFrontendPool as $frontend) {
            $frontend->getBackend()->clean();
        }
        return [
            'success' => true,
            'message' => 'All caches have been flushed',
            'flushed' => $flushed,
        ];
    }

    private function flushType(string $cacheType): array
    {
        if (!$cacheType) {
            return ['error' => 'cache_type parameter is required for flush_type action'];
        }

        $types = $this->cacheTypeList->getTypes();
        if (!isset($types[$cacheType])) {
            return ['error' => 'Unknown cache type: ' . $cacheType . '. Use "status" action to list available types.'];
        }

        $this->cacheTypeList->cleanType($cacheType);

        return [
            'success' => true,
            'message' => sprintf('Cache type "%s" has been flushed', $cacheType),
        ];
    }
}
