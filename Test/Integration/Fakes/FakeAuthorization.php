<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Integration\Fakes;

use Magento\Framework\AuthorizationInterface;

final class FakeAuthorization implements AuthorizationInterface
{
    /**
     * @param array<string, bool> $allowedResources keyed by ACL resource id
     */
    public function __construct(private readonly array $allowedResources = [])
    {
    }

    public function isAllowed($resource, $privilege = null): bool
    {
        return $this->allowedResources[$resource] ?? false;
    }
}
