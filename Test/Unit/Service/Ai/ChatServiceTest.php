<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Test\Unit\Service\Ai;

use MageOS\AiBase\Api\AiClientInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use MaggyAssistant\Base\Api\Config\RepositoryInterface;
use MaggyAssistant\Base\Logger\DebugLogger;
use MaggyAssistant\Base\Logger\ErrorLogger;
use MaggyAssistant\Base\Service\Ai\AnswerWidgets;
use MaggyAssistant\Base\Service\Ai\ChatService;
use MaggyAssistant\Base\Service\Ai\Client;
use MaggyAssistant\Base\Service\Skills\PermissionChecker;
use MaggyAssistant\Base\Service\Store\StoreScopeContext;
use MaggyAssistant\Base\Service\Tool\ToolRegistry;
use MaggyAssistant\Base\Service\Usage\UsageLogger;
use MaggyAssistant\Base\Test\Unit\Fakes\BuildsStoreLayouts;
use MaggyAssistant\Base\Test\Unit\Fakes\FakeAction;
use MaggyAssistant\Base\Test\Unit\Fakes\FakeConfigRepository;
use MaggyAssistant\Base\Test\Unit\Fakes\FakeLogger;
use MaggyAssistant\Base\Test\Unit\Fakes\FakeSkill;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChatServiceTest extends TestCase
{
    use BuildsStoreLayouts;

    private const SYSTEM_PROMPT = 'You are a Magento store assistant.';

    private const ADMIN_ID = 7;

    /** @var array<int, array<string, mixed>> Messages the provider received on the last call */
    private array $sentMessages = [];

    /** @var list<array<string, mixed>> Messages the provider received per chat() call */
    private array $requests = [];

    /** @var list<array<string, mixed>> Canned provider responses, consumed in order */
    private array $responses = [];

    /** @var array<string, string> skill name => read|write|disabled */
    private array $grants = [];

    private ChatService $chatService;

    protected function setUp(): void
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturn(true);

        $cmsData = new FakeSkill('cms_data', $authorization, [
            'list_pages' => new FakeAction('list_pages', true, [], 'Always mention the page count.'),
            'update_page' => new FakeAction('update_page', false, ['content' => ['type' => 'string']]),
        ]);
        $this->grants = ['cms_data' => 'read'];

        $checker = $this->createMock(PermissionChecker::class);
        $checker->method('isAllowed')->willReturnCallback(
            fn (int $adminUserId, string $skill, string $action): bool => match ($this->grants[$skill] ?? 'disabled') {
                'write' => true,
                'read' => $action === 'read',
                default => false,
            }
        );

        $client = $this->createMock(Client::class);
        $client->method('resolve')->willReturn($this->createMock(AiClientInterface::class));
        $client->method('chat')->willReturnCallback(function (AiClientInterface $c, array $messages): array {
            $this->requests[] = $messages;
            return array_shift($this->responses) ?? ['content' => 'done', 'tool_calls' => []];
        });
        $client->method('stream')->willReturnCallback(function (AiClientInterface $c, array $messages): array {
            $this->requests[] = $messages;
            return array_shift($this->responses) ?? ['content' => 'done', 'tool_calls' => []];
        });

        $json = new Json();
        $this->chatService = new ChatService(
            (new FakeConfigRepository())->withMaxToolIterations(5),
            $client,
            new ToolRegistry($checker, [$cmsData]),
            new DebugLogger(new FakeLogger(), $json),
            new ErrorLogger(new FakeLogger(), $json),
            $this->createMock(UsageLogger::class),
            $authorization,
            new StoreScopeContext($this->singleStoreManager()),
            new AnswerWidgets()
        );
    }

    #[Test]
    public function deniedWriteActionIsAnsweredImmediatelyInsteadOfAskingForConfirmation(): void
    {
        $this->responses = [
            $this->toolCallResponse('update_page', ['content' => 'x']),
            ['content' => 'Sorry, I cannot do that.', 'tool_calls' => []],
        ];

        $result = $this->chatService->processMessage([$this->userMessage()], null, self::ADMIN_ID);

        self::assertArrayNotHasKey('pending_confirmation', $result);
        self::assertSame('Sorry, I cannot do that.', $result['content']);
        self::assertCount(2, $this->requests);

        $toolResult = $this->lastMessageOfRole($this->requests[1], 'tool');
        self::assertStringContainsString('Access denied', $toolResult['content']);
        self::assertStringContainsString('cms_data', $toolResult['content']);
    }

    #[Test]
    public function permittedWriteActionStillRequiresConfirmation(): void
    {
        $this->grants = ['cms_data' => 'write'];
        $this->responses = [$this->toolCallResponse('update_page', ['content' => 'x'])];

        $result = $this->chatService->processMessage([$this->userMessage()], null, self::ADMIN_ID);

        self::assertTrue($result['pending_confirmation']);
        self::assertCount(1, $this->requests);
    }

    #[Test]
    public function instructionsAreNotInjectedForADeniedCall(): void
    {
        $this->responses = [$this->toolCallResponse('update_page', ['content' => 'x'])];

        $this->chatService->processMessage([$this->userMessage()], null, self::ADMIN_ID);

        self::assertNull($this->instructionMessage($this->requests[1]));
    }

    #[Test]
    public function instructionsAreInjectedAfterAnExecutedCall(): void
    {
        $this->responses = [$this->toolCallResponse('list_pages')];

        $this->chatService->processMessage([$this->userMessage()], null, self::ADMIN_ID);

        $instruction = $this->instructionMessage($this->requests[1]);
        self::assertNotNull($instruction);
        self::assertStringContainsString('Always mention the page count.', $instruction['content']);
    }

    #[Test]
    public function streamingDoesNotAnnounceADeniedCallAsRunningNorAskForConfirmation(): void
    {
        $this->responses = [
            $this->toolCallResponse('update_page', ['content' => 'x']),
            ['content' => 'Sorry, I cannot do that.', 'tool_calls' => []],
        ];
        $events = [];
        $onChunk = static function (string $type, array $data) use (&$events): void {
            $events[] = $type;
        };

        $result = $this->chatService->processMessageStreaming([$this->userMessage()], $onChunk, null, self::ADMIN_ID);

        self::assertArrayNotHasKey('pending_confirmation', $result);
        self::assertNotContains('confirm', $events);
        self::assertNotContains('tool_status', $events);
        self::assertStringContainsString('Access denied', $this->lastMessageOfRole($this->requests[1], 'tool')['content']);
    }

    #[Test]
    public function streamingAnnouncesAnExecutedReadCall(): void
    {
        $this->responses = [$this->toolCallResponse('list_pages')];
        $events = [];
        $onChunk = static function (string $type, array $data) use (&$events): void {
            $events[] = $type . ':' . ($data['status'] ?? '');
        };

        $this->chatService->processMessageStreaming([$this->userMessage()], $onChunk, null, self::ADMIN_ID);

        self::assertSame(['tool_status:running', 'tool_status:done'], $events);
    }

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

    #[Test]
    public function itAppendsTheWidgetGuideAfterTheStoreScopeWhenAnswerWidgetsAreOn(): void
    {
        $service = $this->serviceWith($this->singleStoreManager(), null, true);

        $service->processMessage([['role' => 'user', 'content' => 'How did we do this week?']]);

        $content = $this->sentMessages[0]['content'];
        self::assertStringStartsWith(self::SYSTEM_PROMPT . "\n\n[Store scope]", $content);
        self::assertStringContainsString("\n\n[Answer widgets]", $content);
        self::assertStringContainsString('"type":"rankedBars"', $content);
        self::assertSame('user', $this->sentMessages[1]['role']);
    }

    #[Test]
    public function itLeavesTheWidgetGuideOutWhenAnswerWidgetsAreOff(): void
    {
        $service = $this->serviceWith($this->singleStoreManager());

        $service->processMessage([['role' => 'user', 'content' => 'Hi']]);

        self::assertStringNotContainsString('[Answer widgets]', $this->sentMessages[0]['content']);
    }

    #[Test]
    public function itNeverInjectsTheWidgetGuideTwice(): void
    {
        $service = $this->serviceWith($this->singleStoreManager(), null, true);

        $service->processMessage([
            ['role' => 'system', 'content' => "Custom prompt\n\n[Store scope] here\n\n[Answer widgets] here"],
            ['role' => 'user', 'content' => 'Hi'],
        ]);

        self::assertSame(['system', 'user'], array_column($this->sentMessages, 'role'));
    }

    private function serviceWith(
        StoreManagerInterface $storeManager,
        ?ErrorLogger $errorLogger = null,
        bool $answerWidgets = false
    ): ChatService {
        $configRepository = $this->createMock(RepositoryInterface::class);
        $configRepository->method('getSystemPrompt')->willReturn(self::SYSTEM_PROMPT);
        $configRepository->method('getMaxToolIterations')->willReturn(1);
        $configRepository->method('isAnswerWidgetsEnabled')->willReturn($answerWidgets);

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
            new StoreScopeContext($storeManager),
            new AnswerWidgets()
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function toolCallResponse(string $action, array $input = []): array
    {
        return [
            'content' => '',
            'tool_calls' => [[
                'id' => 'call_1',
                'name' => 'cms_data',
                'input' => ['action' => $action] + $input,
            ]],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function userMessage(): array
    {
        return ['role' => 'user', 'content' => 'Update the home page'];
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @return array<string, mixed>
     */
    private function lastMessageOfRole(array $messages, string $role): array
    {
        $matching = array_values(array_filter($messages, static fn (array $m): bool => ($m['role'] ?? '') === $role));
        self::assertNotEmpty($matching, "No message with role {$role}");

        return end($matching);
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @return array<string, mixed>|null
     */
    private function instructionMessage(array $messages): ?array
    {
        foreach ($messages as $message) {
            if (($message['role'] ?? '') === 'system' && str_starts_with($message['content'], '[Instructions for')) {
                return $message;
            }
        }

        return null;
    }
}
