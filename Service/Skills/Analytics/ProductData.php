<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Skills\Analytics;

use MaggyAssistant\Base\Service\Skills\AbstractSkill;

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
