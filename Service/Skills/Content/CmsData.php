<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Skills\Content;

use MaggyAssistant\Base\Service\Skills\AbstractSkill;

class CmsData extends AbstractSkill
{
    public function getName(): string
    {
        return 'cms_data';
    }

    protected function getBaseDescription(): string
    {
        return 'Manage CMS pages and blocks.';
    }

    protected function getBaseInstructions(): string
    {
        return 'store_id only applies to create_page and create_block. Updating a page or block keeps its existing '
            . 'store view assignment; store_id is ignored there.';
    }
}
