<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Command;

use MaggyAssistant\Base\Api\Command\CommandInterface;

class CommandRegistry
{
    /** @var array<string, CommandInterface> Keyed by lower-cased command name */
    private array $commands = [];

    /**
     * @param CommandInterface[] $commands
     */
    public function __construct(array $commands = [])
    {
        foreach ($commands as $command) {
            $this->commands[strtolower($command->getName())] = $command;
        }
    }

    /**
     * All registered commands, regardless of who is asking
     *
     * @return CommandInterface[]
     */
    public function getAll(): array
    {
        return array_values($this->commands);
    }

    /**
     * Commands the admin may run at least one subcommand of
     *
     * @param int|null $adminUserId
     * @return CommandInterface[]
     */
    public function getAvailable(?int $adminUserId): array
    {
        return array_values(array_filter(
            $this->commands,
            static fn (CommandInterface $command): bool => $command->isAvailable($adminUserId)
        ));
    }

    public function get(string $name): ?CommandInterface
    {
        return $this->commands[strtolower($name)] ?? null;
    }
}
