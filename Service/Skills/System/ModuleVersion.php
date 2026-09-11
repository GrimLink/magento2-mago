<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Skills\System;

use MagoAssistant\Mago\Service\Skills\AbstractSkill;

class ModuleVersion extends AbstractSkill
{
    public function getName(): string
    {
        return 'module_version';
    }

    protected function getBaseDescription(): string
    {
        return 'Look up the installed version of a Magento module or third-party extension by name.';
    }

    public function getMagentoAcl(array $input = []): string
    {
        return 'Magento_Config::dev';
    }

    protected function getBaseInstructions(): string
    {
        return 'Installed module and extension versions can reveal what security patches a store has or '
            . "hasn't applied, so this is gated behind the Developer ACL (admin/developer roles only).";
    }
}
