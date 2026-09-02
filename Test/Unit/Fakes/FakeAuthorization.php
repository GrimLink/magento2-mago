<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Test\Unit\Fakes;

use Magento\Framework\AuthorizationInterface;

final class FakeAuthorization implements AuthorizationInterface
{
    public function isAllowed($resource, $privilege = null): bool
    {
        return true;
    }
}
