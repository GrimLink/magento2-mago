<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Privacy;

use MagoAssistant\Mago\Service\Privacy\ConversationVault;
use MagoAssistant\Mago\Service\Privacy\PiiClassificationRegistry;
use MagoAssistant\Mago\Service\Privacy\PiiHeuristic;
use MagoAssistant\Mago\Service\Privacy\PrivacyFilter;
use MagoAssistant\Mago\Service\Privacy\PrivacyService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PrivacyServiceTest extends TestCase
{
    private function service(ConversationVault $vault): PrivacyService
    {
        return new PrivacyService(new PrivacyFilter(new PiiClassificationRegistry(), $vault), $vault, new PiiHeuristic());
    }

    #[Test]
    public function itScrubsPiiTypedIntoTheUserMessageBeforeItReachesTheLlm(): void
    {
        $messages = $this->service(new ConversationVault())->scrubMessages([
            ['role' => 'system', 'content' => 'You are an assistant.'],
            ['role' => 'user', 'content' => 'Email jan@example.com about order 000000549'],
        ]);

        self::assertSame('Email [email_1] about order 000000549', $messages[1]['content']);
        self::assertStringNotContainsString('jan@example.com', (string)json_encode($messages));
    }

    #[Test]
    public function theSameTypedValueKeepsItsTokenAcrossMessages(): void
    {
        $service = $this->service(new ConversationVault());

        $first = $service->scrubMessages([['role' => 'user', 'content' => 'mail jan@example.com']]);
        $second = $service->scrubMessages([['role' => 'user', 'content' => 'again jan@example.com']]);

        self::assertSame('mail [email_1]', $first[0]['content']);
        self::assertSame('again [email_1]', $second[0]['content']);
    }

    #[Test]
    public function itRehydratesTokensForTheAdmin(): void
    {
        $vault = new ConversationVault();
        $service = $this->service($vault);
        $service->scrubMessages([['role' => 'user', 'content' => 'call 0612345678']]);

        self::assertSame('I will call 0612345678', $service->rehydrate('I will call [phone_1]'));
    }
}
