<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Fakes;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FakeConfigRepositoryTest extends TestCase
{
    #[Test]
    public function itImplementsEveryConfigRepositoryGetter(): void
    {
        $configRepository = new FakeConfigRepository();

        self::assertSame(0, $configRepository->getPayloadRetentionDays());
        self::assertSame('', $configRepository->getAiServiceId());
        self::assertSame(0, $configRepository->getMaxResponseTokens());
    }
}
