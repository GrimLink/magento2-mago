<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Api\Skill;

/**
 * Skill action interface — individual action within a skill tool
 * @api
 */
interface ActionInterface
{
    /**
     * Action identifier, e.g. 'recent_orders'
     */
    public function getName(): string;

    /**
     * Human-readable description for AI tool definitions
     */
    public function getDescription(): string;

    /**
     * Extra parameter definitions (beyond 'action') as JSON Schema properties
     */
    public function getParameterSchema(): array;

    /**
     * ACL resource required for this action, null = no extra check
     */
    public function getAclResource(): ?string;

    /**
     * Whether this action only reads data (no side effects)
     */
    public function isReadOnly(): bool;

    /**
     * Execute the action
     *
     * @param array $params Tool call parameters
     * @param int $adminUserId Current admin user ID
     * @return array Result data
     */
    public function execute(array $params, int $adminUserId): array;

    /**
     * Detailed instructions for this action, injected after execution.
     * Return empty string if not needed.
     *
     * @return string
     */
    public function getInstructions(): string;
}
