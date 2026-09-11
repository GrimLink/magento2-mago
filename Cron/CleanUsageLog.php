<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Cron;

use MagoAssistant\Mago\Service\Usage\UsageLogCleaner;

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
