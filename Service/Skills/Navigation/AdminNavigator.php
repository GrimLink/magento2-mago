<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Skills\Navigation;

use MaggyAssistant\Base\Api\Tool\ToolInterface;
use MaggyAssistant\Base\Service\Url\SecureAdminUrl;

class AdminNavigator implements ToolInterface
{
    private const ENTITY_ROUTES = [
        'order' => 'sales/order/view',
        'invoice' => 'sales/invoice/view',
        'shipment' => 'sales/shipment/view',
        'creditmemo' => 'sales/creditmemo/view',
        'customer' => 'customer/index/edit',
        'product' => 'catalog/product/edit',
        'cms_page' => 'cms/page/edit',
        'cms_block' => 'cms/block/edit',
        'category' => 'catalog/category/edit',
    ];

    private const ENTITY_PARAM_KEYS = [
        'order' => 'order_id',
        'invoice' => 'invoice_id',
        'shipment' => 'shipment_id',
        'creditmemo' => 'creditmemo_id',
        'customer' => 'id',
        'product' => 'id',
        'cms_page' => 'page_id',
        'cms_block' => 'block_id',
        'category' => 'id',
    ];

    public function __construct(
        private readonly PageRegistry $pageRegistry,
        private readonly SecureAdminUrl $secureAdminUrl
    ) {
    }

    public function getName(): string
    {
        return 'admin_navigator';
    }

    public function getDescription(): string
    {
        return 'Find direct clickable links to Magento admin pages. Two modes: '
            . '(1) Search: pass "query" to find admin pages by keyword. '
            . '(2) Direct link: pass "entity_type" + "entity_id" to get a link to a specific record '
            . '(use after fetching entity data from sales_data, customer_data, or product_data). '
            . 'ALWAYS use this tool when the user asks where to find something in the admin. '
            . 'ALWAYS include the returned URLs as markdown links in your response, '
            . 'e.g. [Orders](https://store.com/admin/sales/order/key/...)';
    }

    public function getParameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Search query to find admin pages (e.g. "orders", "store name", "cache")',
                ],
                'entity_type' => [
                    'type' => 'string',
                    'enum' => array_keys(self::ENTITY_ROUTES),
                    'description' => 'Entity type for direct link (use with entity_id)',
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'Entity ID (the internal Magento entity_id, not the increment_id)',
                ],
                'category' => [
                    'type' => 'string',
                    'enum' => ['Dashboard', 'Sales', 'Catalog', 'Customers', 'Marketing', 'Content', 'Reports', 'Stores', 'System'],
                    'description' => 'Optional: filter search results by category',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of search results (default: 5)',
                ],
            ],
        ];
    }

    public function execute(array $params): array
    {
        // Direct entity link mode
        if (!empty($params['entity_type']) && !empty($params['entity_id'])) {
            return $this->resolveEntityLink(
                (string)$params['entity_type'],
                (int)$params['entity_id']
            );
        }

        // Search mode
        $query = $params['query'] ?? '';
        if (trim($query) === '') {
            return ['error' => 'Provide either "query" for search or "entity_type" + "entity_id" for a direct link'];
        }

        $category = $params['category'] ?? null;
        $limit = max(1, min((int)($params['limit'] ?? 5), 10));
        $matches = $this->pageRegistry->search($query, $limit);

        if ($category !== null) {
            $matches = array_values(array_filter(
                $matches,
                fn(array $m) => mb_strtolower($m['category']) === mb_strtolower($category)
            ));
        }

        if (empty($matches)) {
            return [
                'results' => [],
                'message' => 'No admin pages found for "' . $query . '". Try different keywords.',
            ];
        }

        $results = [];
        foreach ($matches as $match) {
            $results[] = [
                'label' => $match['label'],
                'category' => $match['category'],
                'url' => $this->secureAdminUrl->getUrl($match['route']),
            ];
        }

        return ['results' => $results];
    }

    private function resolveEntityLink(string $entityType, int $entityId): array
    {
        $route = self::ENTITY_ROUTES[$entityType] ?? null;
        $paramKey = self::ENTITY_PARAM_KEYS[$entityType] ?? null;

        if (!$route || !$paramKey) {
            return ['error' => 'Unknown entity type: ' . $entityType];
        }

        $url = $this->secureAdminUrl->getUrl($route, [$paramKey => $entityId]);

        return [
            'results' => [[
                'label' => ucfirst(str_replace('_', ' ', $entityType)) . ' #' . $entityId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'url' => $url,
            ]],
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

    public function getRequiredAcl(): string
    {
        return 'MaggyAssistant_Base::assistant_read';
    }

    public function getInstructions(): string
    {
        return '';
    }

    public function getMagentoAcl(): string
    {
        return '';
    }
}
