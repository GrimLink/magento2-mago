<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Cron;

use MagoAssistant\Mago\Service\Docs\DocsSyncService;

class SyncDocs
{
    public function __construct(
        private readonly DocsSyncService $syncService
    ) {
    }

    public function execute(): void
    {
        $this->syncService->sync(false);
    }
}
