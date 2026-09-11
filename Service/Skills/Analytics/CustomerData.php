<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Skills\Analytics;

use MagoAssistant\Mago\Service\Skills\AbstractSkill;

class CustomerData extends AbstractSkill
{
    public function getName(): string
    {
        return 'customer_data';
    }

    protected function getBaseDescription(): string
    {
        return 'Query customer data: counts, recent signups, top spenders, customer lookup.';
    }

    protected function getBaseInstructions(): string
    {
        return 'All customer results include admin_url – always include these as markdown links in your response. '
            . 'Do NOT call admin_navigator separately for customers, the URLs are already in the data.';
    }
}
