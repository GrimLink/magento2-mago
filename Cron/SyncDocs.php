<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Cron;

use MaggyAssistant\Base\Service\Docs\DocsSyncService;

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
