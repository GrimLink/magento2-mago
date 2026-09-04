<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Test\Unit\Fakes;

use MaggyAssistant\Base\Api\Skill\ActionInterface;

final class FakeAction implements ActionInterface
{
    /**
     * @param array<string, array<string, mixed>> $parameterSchema
     */
    public function __construct(
        private readonly string $name,
        private readonly bool $isReadOnly,
        private readonly array $parameterSchema = [],
        private readonly string $instructions = ''
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
        return ['executed' => $this->name, 'admin_user_id' => $adminUserId];
    }

    public function getInstructions(): string
    {
        return $this->instructions;
    }
}
