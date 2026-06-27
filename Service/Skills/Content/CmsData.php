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

    public function getRequiredAcl(): string
    {
        return 'MaggyAssistant_Base::assistant_write';
    }
}
