<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Test\Unit\Service\Ai;

use MageOS\AiBase\Api\AiClientInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Store\Model\StoreManagerInterface;
use MaggyAssistant\Base\Api\Config\RepositoryInterface;
use MaggyAssistant\Base\Logger\DebugLogger;
use MaggyAssistant\Base\Logger\ErrorLogger;
use MaggyAssistant\Base\Service\Ai\ChatService;
use MaggyAssistant\Base\Service\Ai\Client;
use MaggyAssistant\Base\Service\Store\StoreScopeContext;
use MaggyAssistant\Base\Service\Tool\ToolRegistry;
use MaggyAssistant\Base\Service\Usage\UsageLogger;
use MaggyAssistant\Base\Test\Unit\Fakes\BuildsStoreLayouts;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ChatServiceTest extends TestCase
{
    use BuildsStoreLayouts;

    private const SYSTEM_PROMPT = 'You are a Magento store assistant.';

    /** @var array<int, array<string, mixed>> Messages the provider received on the last call */
    private array $sentMessages = [];

    #[Test]
    public function itAppendsTheStoreScopeToTheSystemPromptOfEveryRequest(): void
    {
        $service = $this->serviceWith($this->multiStoreManager());

        $service->processMessage([['role' => 'user', 'content' => 'Change the store name']]);

        self::assertSame('system', $this->sentMessages[0]['role']);
        self::assertStringStartsWith(self::SYSTEM_PROMPT . "\n\n[Store scope]", $this->sentMessages[0]['content']);
        self::assertStringContainsString('Store view "Luma" (id 2, code "luma")', $this->sentMessages[0]['content']);
        self::assertSame('user', $this->sentMessages[1]['role']);
    }

    #[Test]
    public function itAddsTheStoreScopeRightAfterACallerSuppliedSystemMessage(): void
    {
        $service = $this->serviceWith($this->multiStoreManager());

        $service->processMessage([
            ['role' => 'system', 'content' => 'Custom prompt'],
            ['role' => 'user', 'content' => 'Hi'],
        ]);

        self::assertSame(['system', 'system', 'user'], array_column($this->sentMessages, 'role'));
        self::assertSame('Custom prompt', $this->sentMessages[0]['content']);
        self::assertStringStartsWith('[Store scope]', $this->sentMessages[1]['content']);
    }

    #[Test]
    public function itNeverInjectsTheStoreScopeTwice(): void
    {
        $service = $this->serviceWith($this->multiStoreManager());

        $service->processMessage([
            ['role' => 'system', 'content' => "Custom prompt\n\n[Store scope] already here"],
            ['role' => 'user', 'content' => 'Hi'],
        ]);

        self::assertSame(['system', 'user'], array_column($this->sentMessages, 'role'));
    }

    #[Test]
    public function itTellsTheAssistantNotToAskOnASingleStoreView(): void
    {
        $service = $this->serviceWith($this->singleStoreManager());

        $service->processMessage([['role' => 'user', 'content' => 'Hi']]);

        self::assertStringContainsString(
            'never ask the user which store view to use',
            $this->sentMessages[0]['content']
        );
    }

    #[Test]
    public function itKeepsChattingWhenTheStoreLayoutCannotBeLoaded(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStores')->willThrowException(new \RuntimeException('stores table gone'));
        $errorLogger = $this->createMock(ErrorLogger::class);
        $errorLogger->expects(self::once())->method('addLog')->with('StoreScopeContext', 'stores table gone');

        $response = $this->serviceWith($storeManager, $errorLogger)
            ->processMessage([['role' => 'user', 'content' => 'Hi']]);

        self::assertSame('ok', $response['content']);
        self::assertSame(self::SYSTEM_PROMPT, $this->sentMessages[0]['content']);
    }

    private function serviceWith(StoreManagerInterface $storeManager, ?ErrorLogger $errorLogger = null): ChatService
    {
        $configRepository = $this->createMock(RepositoryInterface::class);
        $configRepository->method('getSystemPrompt')->willReturn(self::SYSTEM_PROMPT);
        $configRepository->method('getMaxToolIterations')->willReturn(1);

        $client = $this->createMock(Client::class);
        $client->method('resolve')->willReturn($this->createMock(AiClientInterface::class));
        $client->method('chat')->willReturnCallback(function (AiClientInterface $aiClient, array $messages): array {
            $this->sentMessages = $messages;
            return ['content' => 'ok', 'tool_calls' => []];
        });

        return new ChatService(
            $configRepository,
            $client,
            new ToolRegistry(null, []),
            $this->createMock(DebugLogger::class),
            $errorLogger ?? $this->createMock(ErrorLogger::class),
            $this->createMock(UsageLogger::class),
            $this->createMock(AuthorizationInterface::class),
            new StoreScopeContext($storeManager)
        );
    }
}
