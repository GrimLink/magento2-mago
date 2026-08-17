<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Usage;

use Magento\Framework\App\ResourceConnection;
use MaggyAssistant\Base\Api\Config\RepositoryInterface as ConfigRepositoryInterface;

class UsageLogger
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ConfigRepositoryInterface $configRepository
    ) {
    }

    public function log(
        int $adminUserId,
        ?int $conversationId,
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        array $skillNames = [],
        ?array $requestPayload = null,
        ?array $responsePayload = null
    ): void {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('maggy_usage_log');

        $data = [
            'admin_user_id' => $adminUserId,
            'conversation_id' => $conversationId,
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $inputTokens + $outputTokens,
            'skill_names' => !empty($skillNames) ? implode(',', $skillNames) : null,
        ];

        // Full payloads can hold store data and PII; only persist them in debug mode (issue #26)
        if ($this->configRepository->isDebugEnabled()) {
            if ($requestPayload !== null) {
                $data['request_payload'] = json_encode($requestPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            if ($responsePayload !== null) {
                $data['response_payload'] = json_encode($responsePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        $connection->insert($table, $data);
    }
}
