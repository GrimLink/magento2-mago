<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Command;

use Magento\Framework\AuthorizationInterface;
use MagoAssistant\Mago\Service\Command\CommandRegistry;
use MagoAssistant\Mago\Service\Command\CommandRunner;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CommandRunnerTest extends TestCase
{
    private const ADMIN_ID = 7;

    private FakeCommand $cache;

    private FakeCommand $hidden;

    protected function setUp(): void
    {
        $this->cache = new FakeCommand('cache');
        $this->hidden = new FakeCommand('secret', false);
    }

    #[Test]
    public function recognisesRegisteredCommandsAndHelpOnly(): void
    {
        $runner = $this->runner();

        self::assertTrue($runner->isCommand('/cache flush'));
        self::assertTrue($runner->isCommand('  /Cache'));
        self::assertTrue($runner->isCommand('/help'));
        self::assertFalse($runner->isCommand('/revenue today'), 'unknown slash prompts stay with the assistant');
        self::assertFalse($runner->isCommand('cache flush'));
        self::assertFalse($runner->isCommand('/'));
        self::assertFalse($runner->isCommand(''));
    }

    #[Test]
    public function parsesNameSubcommandAndArgumentsCaseInsensitively(): void
    {
        $parsed = $this->runner()->parse("/Cache  CLEAN config   full_page\n");

        self::assertSame(
            ['name' => 'cache', 'subcommand' => 'clean', 'args' => ['config', 'full_page']],
            $parsed
        );
    }

    #[Test]
    public function dispatchesToTheCommandWithArgumentsAndAdminUser(): void
    {
        $reply = $this->runner()->run('/cache apply config', self::ADMIN_ID, $this->noopChunk());

        self::assertSame('ran apply config', $reply);
        self::assertSame(
            [['subcommand' => 'apply', 'args' => ['config'], 'adminUserId' => self::ADMIN_ID]],
            $this->cache->executions
        );
    }

    #[Test]
    public function missingOrUnknownSubcommandRendersUsage(): void
    {
        $runner = $this->runner();

        foreach (['/cache', '/cache help', '/cache nonsense'] as $message) {
            $reply = $runner->run($message, self::ADMIN_ID, $this->noopChunk());

            self::assertStringContainsString('**/cache** — Fake cache command', $reply, $message);
            self::assertStringContainsString('| `/cache show` | Show something |', $reply, $message);
            self::assertStringContainsString('| `/cache apply <target>` | Change something |', $reply, $message);
        }
        self::assertSame([], $this->cache->executions);
    }

    #[Test]
    public function helpListsOnlyAvailableCommands(): void
    {
        $reply = $this->runner()->run('/help', self::ADMIN_ID, $this->noopChunk());

        self::assertStringContainsString('`/cache show`', $reply);
        self::assertStringContainsString('`/cache apply <target>`', $reply);
        self::assertStringNotContainsString('/secret', $reply);
    }

    #[Test]
    public function writeSubcommandNeedsTheWriteAclAndIsHiddenFromUsageWithoutIt(): void
    {
        $runner = $this->runner(false);

        $reply = $runner->run('/cache apply config', self::ADMIN_ID, $this->noopChunk());
        self::assertStringContainsString('MagoAssistant_Mago::assistant_write', $reply);
        self::assertSame([], $this->cache->executions);

        $usage = $runner->run('/cache', self::ADMIN_ID, $this->noopChunk());
        self::assertStringContainsString('`/cache show`', $usage);
        self::assertStringNotContainsString('apply', $usage);

        self::assertSame('ran show ', $runner->run('/cache show', self::ADMIN_ID, $this->noopChunk()));
    }

    #[Test]
    public function availableSubcommandsHonourWriteAclAndSkillGrant(): void
    {
        $readOnlyCache = new FakeCommand('cache', true, false);

        $withAcl = new CommandRunner(new CommandRegistry([$readOnlyCache]), $this->authorization(true));
        self::assertSame(['show'], array_keys($withAcl->getAvailableSubcommands($readOnlyCache, self::ADMIN_ID)));

        self::assertSame(
            ['show', 'apply'],
            array_keys($this->runner()->getAvailableSubcommands($this->cache, self::ADMIN_ID))
        );
        self::assertSame(
            ['show'],
            array_keys($this->runner(false)->getAvailableSubcommands($this->cache, self::ADMIN_ID))
        );
    }

    #[Test]
    public function skillPermissionDenialOnTheSubcommandIsReported(): void
    {
        $readOnlyCache = new FakeCommand('cache', true, false);
        $runner = new CommandRunner(new CommandRegistry([$readOnlyCache]), $this->authorization(true));

        $reply = $runner->run('/cache apply config', self::ADMIN_ID, $this->noopChunk());

        self::assertSame('Your skill permissions do not allow `/cache apply`.', $reply);
        self::assertSame([], $readOnlyCache->executions);
    }

    #[Test]
    public function unavailableCommandIsRefused(): void
    {
        $reply = $this->runner()->run('/secret show', self::ADMIN_ID, $this->noopChunk());

        self::assertSame('You do not have permission to use `/secret`.', $reply);
        self::assertSame([], $this->hidden->executions);
    }

    #[Test]
    public function confirmableToolCallsReturnsThePermittedWritesCalls(): void
    {
        self::assertSame(
            [['id' => 'slash_apply_0', 'name' => 'fake_tool', 'input' => ['args' => ['config']]]],
            $this->runner()->confirmableToolCalls('/cache apply config', self::ADMIN_ID)
        );
    }

    #[Test]
    public function confirmableToolCallsIsEmptyForReadsHelpAndUnknownCommands(): void
    {
        $runner = $this->runner();

        self::assertSame([], $runner->confirmableToolCalls('/cache show', self::ADMIN_ID), 'read subcommand');
        self::assertSame([], $runner->confirmableToolCalls('/help', self::ADMIN_ID), 'help');
        self::assertSame([], $runner->confirmableToolCalls('/revenue today', self::ADMIN_ID), 'not a command');
        self::assertSame([], $runner->confirmableToolCalls('/secret show', self::ADMIN_ID), 'unavailable command');
    }

    #[Test]
    public function confirmableToolCallsIsEmptyWhenTheWriteAclIsMissing(): void
    {
        self::assertSame(
            [],
            $this->runner(false)->confirmableToolCalls('/cache apply config', self::ADMIN_ID),
            'a write the session cannot run stays on run(), which renders the denial'
        );
    }

    #[Test]
    public function confirmableToolCallsIsEmptyWhenTheSkillGrantForbidsTheWrite(): void
    {
        $readOnlyCache = new FakeCommand('cache', true, false);
        $runner = new CommandRunner(new CommandRegistry([$readOnlyCache]), $this->authorization(true));

        $calls = $runner->confirmableToolCalls('/cache apply config', self::ADMIN_ID);

        self::assertSame([], $calls, 'a write the skill grant forbids stays on run(), which renders the denial');
    }

    #[Test]
    public function confirmableToolCallsIsEmptyForAPermittedWriteThatMapsToNoCall(): void
    {
        $calls = $this->runner()->confirmableToolCalls('/cache apply', self::ADMIN_ID);

        self::assertSame([], $calls, 'a write without a target stays on run(), which renders the usage prompt');
    }

    private function runner(bool $canWrite = true): CommandRunner
    {
        return new CommandRunner(
            new CommandRegistry([$this->cache, $this->hidden]),
            $this->authorization($canWrite)
        );
    }

    private function authorization(bool $canWrite): AuthorizationInterface
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturnCallback(
            static fn (string $resource): bool => $resource !== CommandRunner::WRITE_ACL || $canWrite
        );

        return $authorization;
    }

    private function noopChunk(): callable
    {
        return static function (string $type, array $data): void {
        };
    }
}
