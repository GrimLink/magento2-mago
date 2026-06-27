<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Skills\Analytics\ProductData;

use MaggyAssistant\Base\Api\Skill\ActionInterface;
use MaggyAssistant\Base\Service\Api\InternalApiClient;

class CountAction implements ActionInterface
{
    public function __construct(
        private readonly InternalApiClient $apiClient
    ) {
    }

    public function getName(): string
    {
        return 'count';
    }

    public function getDescription(): string
    {
        return 'Total product count (enabled/disabled breakdown)';
    }

    public function getParameterSchema(): array
    {
        return [];
    }

    public function getAclResource(): ?string
    {
        return null;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getInstructions(): string
    {
        return '';
    }

    public function execute(array $params, int $adminUserId): array
    {
        if (!$adminUserId) {
            return ['error' => 'Admin user context is required'];
        }

        $totalParams = $this->apiClient->buildSearchCriteria([], 1);
        $totalResult = $this->apiClient->get('products', $totalParams, $adminUserId);

        if (isset($totalResult['error'])) {
            return $totalResult;
        }

        $total = $totalResult['total_count'] ?? 0;

        $enabledParams = $this->apiClient->buildSearchCriteria(
            [['field' => 'status', 'value' => '1', 'condition_type' => 'eq']],
            1
        );
        $enabledResult = $this->apiClient->get('products', $enabledParams, $adminUserId);
        $enabledCount = $enabledResult['total_count'] ?? 0;

        return [
            'total_products' => $total,
            'enabled_products' => $enabledCount,
            'disabled_products' => $total - $enabledCount,
        ];
    }
}
