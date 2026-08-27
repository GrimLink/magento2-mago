<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Skills\Configuration;

use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Indexer\Model\Indexer\CollectionFactory;
use MaggyAssistant\Base\Api\Tool\ToolInterface;

class IndexerManager implements ToolInterface
{
    public function __construct(
        private readonly CollectionFactory $indexerCollectionFactory,
        private readonly IndexerRegistry $indexerRegistry
    ) {
    }

    public function getName(): string
    {
        return 'indexer_manager';
    }

    public function getDescription(): string
    {
        return 'Manage Magento indexers. Actions: "status" (list all indexers with status), '
            . '"reindex" (reindex a specific indexer by ID, e.g. "catalog_product_price", "catalogsearch_fulltext"), '
            . '"reindex_all" (reindex all indexers), '
            . '"set_mode" (set indexer mode to "realtime" or "schedule").';
    }

    public function getParameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'description' => 'The action to perform',
                    'enum' => ['status', 'reindex', 'reindex_all', 'set_mode'],
                ],
                'indexer_id' => [
                    'type' => 'string',
                    'description' => 'Indexer ID for reindex/set_mode actions (e.g. "catalog_product_price", "catalogsearch_fulltext", "catalog_category_product")',
                ],
                'mode' => [
                    'type' => 'string',
                    'description' => 'Indexer mode for set_mode action',
                    'enum' => ['realtime', 'schedule'],
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
            'reindex' => $this->reindex($params['indexer_id'] ?? ''),
            'reindex_all' => $this->reindexAll(),
            'set_mode' => $this->setMode($params['indexer_id'] ?? '', $params['mode'] ?? ''),
            default => ['error' => 'Unknown action: ' . $action],
        };
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function isReadOnlyAction(array $input): bool
    {
        // status only reads indexer state; reindex/reindex_all/set_mode mutate. Unknown actions fail closed to write.
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
        return 'Magento_Indexer::changeMode';
    }

    private function getStatus(): array
    {
        $collection = $this->indexerCollectionFactory->create();
        $result = [];
        foreach ($collection->getItems() as $indexer) {
            $result[] = [
                'id' => $indexer->getId(),
                'title' => $indexer->getTitle(),
                'status' => $indexer->getStatus(),
                'mode' => $indexer->isScheduled() ? 'schedule' : 'realtime',
                'updated' => $indexer->getUpdated(),
            ];
        }
        return ['indexers' => $result];
    }

    private function reindex(string $indexerId): array
    {
        if (!$indexerId) {
            return ['error' => 'indexer_id parameter is required for reindex action'];
        }

        try {
            $indexer = $this->indexerRegistry->get($indexerId);
        } catch (\Exception $e) {
            return ['error' => 'Unknown indexer: ' . $indexerId . '. Use "status" action to list available indexers.'];
        }

        $indexer->reindexAll();

        return [
            'success' => true,
            'message' => sprintf('Indexer "%s" has been reindexed', $indexer->getTitle()),
        ];
    }

    private function reindexAll(): array
    {
        $collection = $this->indexerCollectionFactory->create();
        $reindexed = [];
        $errors = [];

        foreach ($collection->getItems() as $indexer) {
            try {
                $indexer->reindexAll();
                $reindexed[] = $indexer->getId();
            } catch (\Exception $e) {
                $errors[] = $indexer->getId() . ': ' . $e->getMessage();
            }
        }

        $result = [
            'success' => empty($errors),
            'message' => sprintf('%d indexers reindexed', count($reindexed)),
            'reindexed' => $reindexed,
        ];
        if ($errors) {
            $result['errors'] = $errors;
        }
        return $result;
    }

    private function setMode(string $indexerId, string $mode): array
    {
        if (!$indexerId) {
            return ['error' => 'indexer_id parameter is required for set_mode action'];
        }
        if (!in_array($mode, ['realtime', 'schedule'])) {
            return ['error' => 'mode must be "realtime" or "schedule"'];
        }

        try {
            $indexer = $this->indexerRegistry->get($indexerId);
        } catch (\Exception $e) {
            return ['error' => 'Unknown indexer: ' . $indexerId];
        }

        $indexer->setScheduled($mode === 'schedule');

        return [
            'success' => true,
            'message' => sprintf('Indexer "%s" mode set to "%s"', $indexer->getTitle(), $mode),
        ];
    }
}
