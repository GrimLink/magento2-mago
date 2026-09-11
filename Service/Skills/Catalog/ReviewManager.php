<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Skills\Catalog;

use MagoAssistant\Mago\Service\Skills\AbstractSkill;

class ReviewManager extends AbstractSkill
{
    public function getName(): string
    {
        return 'review_manager';
    }

    protected function getBaseDescription(): string
    {
        return 'Manage product reviews: list pending/approved, approve, reject, get stats.';
    }

    public function getMagentoAcl(array $input = []): string
    {
        return 'Magento_Review::reviews_all';
    }

    protected function getBaseInstructions(): string
    {
        return 'All review results include admin_url – always include these as markdown links in your response. '
            . 'Do NOT call admin_navigator separately for reviews, the URLs are already in the data.';
    }
}
