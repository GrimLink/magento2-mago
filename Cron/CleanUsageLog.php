<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Cron;

use MaggyAssistant\Base\Service\Usage\UsageLogCleaner;

class CleanUsageLog
{
    public function __construct(
        private readonly UsageLogCleaner $cleaner
    ) {
    }

    public function execute(): void
    {
        $this->cleaner->clean();
    }
}
