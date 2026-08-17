<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Usage;

use Magento\Framework\App\ResourceConnection;
use MaggyAssistant\Base\Api\Config\RepositoryInterface as ConfigRepositoryInterface;

class UsageLogCleaner
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ConfigRepositoryInterface $configRepository
    ) {
    }

    /**
     * @return int
     */
    public function clean(): int
    {
        $days = $this->configRepository->getPayloadRetentionDays();
        if ($days === 0) {
            return 0;
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('maggy_usage_log');
        $cutoff = (new \DateTimeImmutable(sprintf('-%d days', $days), new \DateTimeZone('UTC')))
            ->format('Y-m-d H:i:s');

        return $connection->update(
            $table,
            ['request_payload' => null, 'response_payload' => null],
            [
                'created_at < ?' => $cutoff,
                'request_payload IS NOT NULL OR response_payload IS NOT NULL',
            ]
        );
    }
}
