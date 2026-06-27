<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Skills\Analytics\SalesData;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression;
use MaggyAssistant\Base\Api\Skill\ActionInterface;
use MaggyAssistant\Base\Service\Skills\PeriodParser;

class RevenueAction implements ActionInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly PeriodParser $periodParser
    ) {
    }

    public function getName(): string
    {
        return 'revenue_summary';
    }

    public function getDescription(): string
    {
        return 'Total revenue, order count, AOV for a period';
    }

    public function getParameterSchema(): array
    {
        return [
            'period' => [
                'type' => 'string',
                'description' => 'Time period: "today", "yesterday", "7days", "30days", "this_month", "last_month", "this_year" or "YYYY-MM-DD:YYYY-MM-DD"',
            ],
        ];
    }

    public function getAclResource(): ?string
    {
        return 'Magento_Sales::sales';
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
        $period = $params['period'] ?? '30days';
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('sales_order');
        [$from, $to] = $this->periodParser->parse($period);

        $select = $connection->select()
            ->from($table, [
                'total_revenue' => new Expression('SUM(grand_total)'),
                'order_count' => new Expression('COUNT(*)'),
                'avg_order_value' => new Expression('AVG(grand_total)'),
                'total_items' => new Expression('SUM(total_item_count)'),
            ])
            ->where('created_at >= ?', $from)
            ->where('created_at <= ?', $to)
            ->where('state NOT IN (?)', ['canceled', 'closed']);

        $result = $connection->fetchRow($select);

        return [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'total_revenue' => round((float)($result['total_revenue'] ?? 0), 2),
            'order_count' => (int)($result['order_count'] ?? 0),
            'average_order_value' => round((float)($result['avg_order_value'] ?? 0), 2),
            'total_items_sold' => (int)($result['total_items'] ?? 0),
        ];
    }
}
