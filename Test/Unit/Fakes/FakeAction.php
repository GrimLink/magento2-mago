<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Fakes;

use MagoAssistant\Mago\Api\Skill\ActionInterface;

final class FakeAction implements ActionInterface
{
    /**
     * @param array<string, array<string, mixed>> $parameterSchema
     * @param array<string, mixed>|null $result What execute() answers; null for the default echo
     */
    public function __construct(
        private readonly string $name,
        private readonly bool $isReadOnly,
        private readonly array $parameterSchema = [],
        private readonly string $instructions = '',
        private readonly ?array $result = null
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->name . ' description';
    }

    public function getParameterSchema(): array
    {
        return $this->parameterSchema;
    }

    public function getAclResource(): ?string
    {
        return null;
    }

    public function isReadOnly(): bool
    {
        return $this->isReadOnly;
    }

    public function execute(array $params, int $adminUserId): array
    {
        return $this->result ?? ['executed' => $this->name, 'admin_user_id' => $adminUserId];
    }

    public function getInstructions(): string
    {
        return $this->instructions;
    }
}
