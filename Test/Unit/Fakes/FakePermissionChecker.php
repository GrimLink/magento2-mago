<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Test\Unit\Fakes;

use MaggyAssistant\Base\Service\Skills\PermissionChecker;

final class FakePermissionChecker extends PermissionChecker
{
    /** @var array<string, bool> */
    private array $decisions = [];

    public function __construct()
    {
    }

    public function withDecision(string $skillName, string $action, bool $isAllowed): self
    {
        $this->decisions[$skillName . ':' . $action] = $isAllowed;

        return $this;
    }

    public function isAllowed(int $adminUserId, string $skillName, string $action = 'read'): bool
    {
        return $this->decisions[$skillName . ':' . $action] ?? false;
    }
}
