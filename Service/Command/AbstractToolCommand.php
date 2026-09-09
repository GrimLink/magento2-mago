<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Command;

use MaggyAssistant\Base\Api\ChatServiceInterface;
use MaggyAssistant\Base\Api\Command\CommandInterface;
use MaggyAssistant\Base\Service\Tool\ToolRegistry;

/**
 * Slash command backed by one of the assistant's tools. Every subcommand becomes a tool call
 * that goes through the same permission and ACL checks as a call the AI would have made.
 */
abstract class AbstractToolCommand implements CommandInterface
{
    /** The admin typed the command, so the call counts as confirmed; one call per run needs no unique id */
    private const TOOL_CALL_ID = 'slash_command';

    public function __construct(
        private readonly ToolRegistry $toolRegistry,
        private readonly ChatServiceInterface $chatService
    ) {
    }

    /**
     * Name of the tool the subcommands are executed with
     */
    abstract protected function getToolName(): string;

    public function isAvailable(?int $adminUserId, ?string $subcommand = null): bool
    {
        $tool = $this->toolRegistry->getTool($this->getToolName(), $adminUserId);
        if ($tool === null) {
            return false;
        }
        if ($subcommand === null) {
            return true;
        }
        $definition = $this->getSubcommands()[$subcommand] ?? null;
        if ($definition === null) {
            return false;
        }

        return $definition['readOnly'] || $this->toolRegistry->hasWriteAccess($tool, $adminUserId);
    }

    /**
     * Execute one tool action; denials and exceptions come back as ['error' => ...]
     *
     * @param array<string, mixed> $input
     * @param int $adminUserId
     * @param callable $onChunk
     * @return array<string, mixed>
     */
    protected function runTool(array $input, int $adminUserId, callable $onChunk): array
    {
        $toolCall = ['id' => self::TOOL_CALL_ID, 'name' => $this->getToolName(), 'input' => $input];
        $results = $this->chatService->executeConfirmedTools([$toolCall], $adminUserId, $onChunk);

        return $results[self::TOOL_CALL_ID] ?? ['error' => 'The tool returned no result'];
    }

    protected function renderError(string $error): string
    {
        return '**Error:** ' . $error;
    }

    /**
     * Markdown table; cells are pipe-escaped so a value cannot break the row
     *
     * @param string[] $headers
     * @param array<int, array<int, string>> $rows
     * @return string
     */
    protected function renderTable(array $headers, array $rows): string
    {
        $escape = static fn (string $cell): string => str_replace('|', '\|', $cell);
        $lines = [
            '| ' . implode(' | ', array_map($escape, $headers)) . ' |',
            '|' . str_repeat('---|', count($headers)),
        ];
        foreach ($rows as $row) {
            $lines[] = '| ' . implode(' | ', array_map($escape, $row)) . ' |';
        }

        return implode("\n", $lines);
    }
}
