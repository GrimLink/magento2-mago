<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Command;

/**
 * /cache — the chat counterpart of bin/magento cache:flush, cache:clean and cache:status
 */
class CacheCommand extends AbstractToolCommand
{
    public function getName(): string
    {
        return 'cache';
    }

    public function getDescription(): string
    {
        return 'Flush, clean or inspect the Magento caches';
    }

    public function getSubcommands(): array
    {
        return [
            'flush' => [
                'args' => '',
                'description' => 'Flush all caches, including the cache storage',
                'readOnly' => false,
            ],
            'clean' => [
                'args' => '<type> [type...]',
                'description' => 'Clean specific cache types, e.g. `config full_page`',
                'readOnly' => false,
            ],
            'status' => [
                'args' => '',
                'description' => 'List all cache types and whether they are enabled',
                'readOnly' => true,
            ],
        ];
    }

    public function execute(string $subcommand, array $args, int $adminUserId, callable $onChunk): string
    {
        return match ($subcommand) {
            'flush' => $this->flush($adminUserId, $onChunk),
            'clean' => $this->clean($args, $adminUserId, $onChunk),
            'status' => $this->status($adminUserId, $onChunk),
            default => $this->renderError('Unknown subcommand: ' . $subcommand),
        };
    }

    protected function getToolName(): string
    {
        return 'cache_manager';
    }

    private function flush(int $adminUserId, callable $onChunk): string
    {
        $result = $this->runTool(['action' => 'flush'], $adminUserId, $onChunk);
        if (isset($result['error'])) {
            return $this->renderError((string)$result['error']);
        }

        $flushed = array_map('strval', (array)($result['flushed'] ?? []));
        $reply = '**All caches flushed.**';
        if ($flushed !== []) {
            $reply .= sprintf(
                "\n\nCleaned %d cache types: %s",
                count($flushed),
                implode(', ', array_map(static fn (string $id): string => '`' . $id . '`', $flushed))
            );
        }

        return $reply;
    }

    /**
     * Clean the given cache types one by one, so one unknown type does not stop the rest
     *
     * @param string[] $types
     * @param int $adminUserId
     * @param callable $onChunk
     * @return string
     */
    private function clean(array $types, int $adminUserId, callable $onChunk): string
    {
        if ($types === []) {
            return $this->renderCleanUsage($adminUserId, $onChunk);
        }

        $cleaned = [];
        $errors = [];
        foreach (array_unique($types) as $type) {
            $result = $this->runTool(['action' => 'flush_type', 'cache_type' => $type], $adminUserId, $onChunk);
            if (isset($result['error'])) {
                $errors[] = (string)$result['error'];
                continue;
            }
            $cleaned[] = '`' . $type . '`';
        }

        $lines = [];
        if ($cleaned !== []) {
            $lines[] = sprintf(
                '**Cleaned %d cache type%s:** %s',
                count($cleaned),
                count($cleaned) === 1 ? '' : 's',
                implode(', ', $cleaned)
            );
        }
        foreach ($errors as $error) {
            $lines[] = $this->renderError($error);
        }

        return implode("\n\n", $lines);
    }

    private function renderCleanUsage(int $adminUserId, callable $onChunk): string
    {
        $reply = 'Usage: `/cache clean <type> [type...]`';
        $result = $this->runTool(['action' => 'status'], $adminUserId, $onChunk);
        $types = $result['cache_types'] ?? null;
        if (!is_array($types) || $types === []) {
            return $reply;
        }

        $ids = array_map(static fn (array $type): string => '`' . (string)($type['id'] ?? '') . '`', $types);

        return $reply . "\n\nAvailable types: " . implode(', ', $ids);
    }

    private function status(int $adminUserId, callable $onChunk): string
    {
        $result = $this->runTool(['action' => 'status'], $adminUserId, $onChunk);
        if (isset($result['error'])) {
            return $this->renderError((string)$result['error']);
        }

        $rows = [];
        foreach ((array)($result['cache_types'] ?? []) as $type) {
            $rows[] = [
                '`' . (string)($type['id'] ?? '') . '`',
                (string)($type['label'] ?? ''),
                ($type['status'] ?? '') === 'enabled' ? 'Enabled' : 'Disabled',
            ];
        }
        if ($rows === []) {
            return 'No cache types found.';
        }

        return "**Cache status**\n\n" . $this->renderTable(['Type', 'Label', 'Status'], $rows);
    }
}
