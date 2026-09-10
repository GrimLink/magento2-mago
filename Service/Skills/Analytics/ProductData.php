<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Skills\Analytics;

use MagoAssistant\Mago\Service\Skills\AbstractSkill;

class ProductData extends AbstractSkill
{
    public function getName(): string
    {
        return 'product_data';
    }

    protected function getBaseDescription(): string
    {
        return 'Query product catalog data.';
    }
}
