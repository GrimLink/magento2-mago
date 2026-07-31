<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Skills\Debug;

use MaggyAssistant\Base\Service\Skills\AbstractSkill;

class ProductDebug extends AbstractSkill
{
    public function getName(): string
    {
        return 'product_debug';
    }

    protected function getBaseDescription(): string
    {
        return 'Debug why a product is hidden or not visible on the storefront.';
    }

    protected function getBaseInstructions(): string
    {
        return <<<INSTRUCTIONS
Use this tool when the user asks why a product is not visible, hidden, or missing from the storefront.
Always require a SKU. Use `diagnose` to get a full per-store-view breakdown of all visibility factors.
INSTRUCTIONS;
    }
}
