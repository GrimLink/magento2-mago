<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Api\Command;

/**
 * Slash command — a chat message the admin types as "/name subcommand [args]" that runs
 * directly against Magento, without a round-trip through the AI provider.
 *
 * @api
 */
interface CommandInterface
{
    /**
     * Command name without the leading slash, e.g. "cache"
     */
    public function getName(): string;

    /**
     * One-line description shown in the slash menu and in /help
     */
    public function getDescription(): string;

    /**
     * Subcommands keyed by name. "args" is the usage hint shown after the subcommand
     * (empty when it takes none), "readOnly" tells whether it mutates the store.
     *
     * @return array<string, array{args: string, description: string, readOnly: bool}>
     */
    public function getSubcommands(): array;

    /**
     * Whether the admin may run this command at all (subcommand null) or the given subcommand.
     * Covers the assistant skill permissions only; native Magento ACL is enforced on execution.
     *
     * @param int|null $adminUserId
     * @param string|null $subcommand
     * @return bool
     */
    public function isAvailable(?int $adminUserId, ?string $subcommand = null): bool;

    /**
     * Run a subcommand and return the reply as Markdown
     *
     * @param string $subcommand
     * @param string[] $args Whitespace-separated arguments after the subcommand
     * @param int $adminUserId
     * @param callable $onChunk fn(string $type, array $data) for tool_status events
     * @return string
     */
    public function execute(string $subcommand, array $args, int $adminUserId, callable $onChunk): string;
}
