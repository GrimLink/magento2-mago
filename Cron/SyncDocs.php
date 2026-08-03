<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Cron;

use MaggyAssistant\Base\Service\Docs\DocsSyncService;

/**
 * Periodic docs sync. Self-no-ops when the source is unchanged and the corpus is fresh
 * (see DocsSyncService::sync). Also performs the initial index when the corpus is empty.
 */
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
