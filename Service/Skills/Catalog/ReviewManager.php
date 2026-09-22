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
        return 'Results carry no admin link: privacy mode strips admin_url before it reaches you, because it embeds the admin secret key. Never write an admin url yourself, not even one that looks right — without that key it opens nothing. Call admin_navigator when the admin asks for a link, and otherwise name the page in words.';
    }
}
