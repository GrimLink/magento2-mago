<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Command;

use Magento\Framework\AuthorizationInterface;
use MagoAssistant\Mago\Api\Command\CommandInterface;

/**
 * Parses "/name subcommand [args]" chat messages and dispatches them to the registered command
 */
class CommandRunner
{
    public const HELP = 'help';

    public const WRITE_ACL = 'MagoAssistant_Mago::assistant_write';

    public function __construct(
        private readonly CommandRegistry $registry,
        private readonly AuthorizationInterface $authorization
    ) {
    }

    /**
     * Whether the message is a registered slash command (or /help). Anything else that
     * starts with a slash is left to the assistant, so a typed "/revenue" still works as a prompt.
     */
    public function isCommand(string $message): bool
    {
        $parsed = $this->parse($message);
        if ($parsed === null) {
            return false;
        }

        return $parsed['name'] === self::HELP || $this->registry->get($parsed['name']) !== null;
    }

    /**
     * Run the command and return the Markdown reply
     *
     * @param string $message
     * @param int $adminUserId
     * @param callable $onChunk fn(string $type, array $data)
     * @return string
     */
    public function run(string $message, int $adminUserId, callable $onChunk): string
    {
        $parsed = $this->parse($message);
        if ($parsed === null) {
            return $this->renderHelp($adminUserId);
        }

        if ($parsed['name'] === self::HELP) {
            return $this->renderHelp($adminUserId);
        }

        $command = $this->registry->get($parsed['name']);
        if ($command === null) {
            return sprintf('Unknown command `/%s`. Type `/help` to see what is available.', $parsed['name']);
        }
        if (!$command->isAvailable($adminUserId)) {
            return sprintf('You do not have permission to use `/%s`.', $command->getName());
        }

        $subcommand = $parsed['subcommand'];
        $subcommands = $command->getSubcommands();
        if ($subcommand === '' || $subcommand === self::HELP || !isset($subcommands[$subcommand])) {
            return $this->renderUsage($command, $adminUserId);
        }

        if (!$subcommands[$subcommand]['readOnly'] && !$this->authorization->isAllowed(self::WRITE_ACL)) {
            return sprintf(
                '`/%s %s` changes the store and needs the "%s" permission, which your admin role does not have.',
                $command->getName(),
                $subcommand,
                self::WRITE_ACL
            );
        }
        if (!$command->isAvailable($adminUserId, $subcommand)) {
            return sprintf(
                'Your skill permissions do not allow `/%s %s`.',
                $command->getName(),
                $subcommand
            );
        }

        return $command->execute($subcommand, $parsed['args'], $adminUserId, $onChunk);
    }

    /**
     * Split "/Name Sub arg1 arg2" into its parts; null when the message is not slash-prefixed
     *
     * @param string $message
     * @return array{name: string, subcommand: string, args: string[]}|null
     */
    public function parse(string $message): ?array
    {
        $message = trim($message);
        if ($message === '' || $message[0] !== '/') {
            return null;
        }

        $tokens = preg_split('/\s+/', trim(substr($message, 1))) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
        if ($tokens === []) {
            return null;
        }

        return [
            'name' => strtolower($tokens[0]),
            'subcommand' => strtolower($tokens[1] ?? ''),
            'args' => array_slice($tokens, 2),
        ];
    }

    private function renderHelp(int $adminUserId): string
    {
        $commands = $this->registry->getAvailable($adminUserId);
        if ($commands === []) {
            return 'No slash commands are available for your account.';
        }

        $lines = ['**Available commands**', '', '| Command | Description |', '|---|---|'];
        foreach ($commands as $command) {
            foreach ($this->usageRows($command, $adminUserId) as $row) {
                $lines[] = $row;
            }
        }
        $lines[] = '';
        $lines[] = 'Type `/<command>` without a subcommand to see its usage.';

        return implode("\n", $lines);
    }

    private function renderUsage(CommandInterface $command, int $adminUserId): string
    {
        $lines = [
            sprintf('**/%s** — %s', $command->getName(), $command->getDescription()),
            '',
            '| Command | Description |',
            '|---|---|',
        ];
        foreach ($this->usageRows($command, $adminUserId) as $row) {
            $lines[] = $row;
        }

        return implode("\n", $lines);
    }

    /**
     * Subcommands the admin may run: write subcommands need the write ACL of the current
     * session and every subcommand needs the command's own (skill grant) approval.
     *
     * @param CommandInterface $command
     * @param int|null $adminUserId
     * @return array<string, array{args: string, description: string, readOnly: bool}>
     */
    public function getAvailableSubcommands(CommandInterface $command, ?int $adminUserId): array
    {
        $canWrite = $this->authorization->isAllowed(self::WRITE_ACL);
        $available = [];
        foreach ($command->getSubcommands() as $name => $definition) {
            if (!$definition['readOnly'] && !$canWrite) {
                continue;
            }
            if (!$command->isAvailable($adminUserId, $name)) {
                continue;
            }
            $available[$name] = $definition;
        }

        return $available;
    }

    /**
     * One Markdown table row per subcommand the admin may run
     *
     * @param CommandInterface $command
     * @param int $adminUserId
     * @return string[]
     */
    private function usageRows(CommandInterface $command, int $adminUserId): array
    {
        $rows = [];
        foreach ($this->getAvailableSubcommands($command, $adminUserId) as $name => $definition) {
            $usage = '/' . $command->getName() . ' ' . $name;
            if ($definition['args'] !== '') {
                $usage .= ' ' . $definition['args'];
            }
            $rows[] = sprintf('| `%s` | %s |', $usage, $definition['description']);
        }

        return $rows;
    }
}
