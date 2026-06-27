<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Skills\Analytics\ProductData;

use MaggyAssistant\Base\Api\Skill\ActionInterface;
use MaggyAssistant\Base\Service\Api\InternalApiClient;

class SearchAction implements ActionInterface
{
    public function __construct(
        private readonly InternalApiClient $apiClient
    ) {
    }

    public function getName(): string
    {
        return 'search';
    }

    public function getDescription(): string
    {
        return 'Search products by name/keyword';
    }

    public function getParameterSchema(): array
    {
        return [
            'query' => [
                'type' => 'string',
                'description' => 'Search query or SKU depending on action',
            ],
            'limit' => [
                'type' => 'integer',
                'description' => 'Number of results (default: 10)',
            ],
        ];
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
        $query = $params['query'] ?? '';
        if (!$query) {
            return ['error' => 'Search query is required'];
        }

        if (!$adminUserId) {
            return ['error' => 'Admin user context is required'];
        }

        $limit = (int)($params['limit'] ?? 10);
        $searchParams = $this->apiClient->buildSearchCriteria(
            [['field' => 'name', 'value' => '%' . $query . '%', 'condition_type' => 'like']],
            $limit
        );
        $result = $this->apiClient->get('products', $searchParams, $adminUserId);

        if (isset($result['error'])) {
            return $result;
        }

        $products = [];
        foreach ($result['items'] ?? [] as $item) {
            $products[] = [
                'sku' => $item['sku'] ?? '',
                'name' => $item['name'] ?? '',
                'price' => (float)($item['price'] ?? 0),
                'status' => ($item['status'] ?? 2) == 1 ? 'enabled' : 'disabled',
                'type' => $item['type_id'] ?? '',
            ];
        }

        return ['query' => $query, 'count' => count($products), 'products' => $products];
    }
}
