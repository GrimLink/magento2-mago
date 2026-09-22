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
        return 'Results carry no admin link: privacy mode strips admin_url before it reaches you, because it embeds the admin secret key. Never write an admin url yourself, not even one that looks right — without that key it opens nothing. Call admin_navigator when the admin asks for a link, and otherwise name the page in words.';
    }
}
