<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Privacy;

use MagoAssistant\Mago\Api\Skill\FieldClassifierInterface;
use MagoAssistant\Mago\Service\Privacy\PiiClass;
use MagoAssistant\Mago\Service\Privacy\PiiClassificationRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PiiClassificationRegistryTest extends TestCase
{
    #[Test]
    public function itServesTheBuiltInClassificationsByDefault(): void
    {
        $registry = new PiiClassificationRegistry();

        self::assertSame(PiiClass::STRIP, $registry->classesFor('lookup_customer')['email'][0]);
        self::assertNull($registry->classesFor('some_unclassified_tool'));
    }

    #[Test]
    public function aThirdPartyClassifierAddsItsOwnActionWithoutTouchingTheCore(): void
    {
        $classifier = new class implements FieldClassifierInterface {
            public function getClassifiedActionName(): string
            {
                return 'vendor_lookup';
            }

            public function getFieldClassification(): array
            {
                return ['email' => [PiiClass::STRIP], 'city' => [PiiClass::PUBLIC]];
            }
        };

        $registry = new PiiClassificationRegistry([$classifier]);

        self::assertSame(PiiClass::STRIP, $registry->classesFor('vendor_lookup')['email'][0]);
        self::assertSame(PiiClass::STRIP, $registry->classesFor('lookup_customer')['email'][0]);
    }
}
