<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Skills\Analytics;

use MaggyAssistant\Base\Service\Skills\AbstractSkill;

class SalesData extends AbstractSkill
{
    public function getName(): string
    {
        return 'sales_data';
    }

    protected function getBaseDescription(): string
    {
        return 'Query sales/order data: revenue, orders, top products, customer orders.';
    }

    protected function getBaseInstructions(): string
    {
        return 'All order results include admin_url – always include these as markdown links in your response. '
            . 'Do NOT call admin_navigator separately for orders, the URLs are already in the data.';
    }
}
