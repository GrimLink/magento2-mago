<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Tool;

use MaggyAssistant\Base\Api\Tool\ToolInterface;
use MaggyAssistant\Base\Service\Skills\PermissionChecker;

class ToolRegistry
{
    /** @var ToolInterface[] */
    private array $tools;

    /**
     * @param PermissionChecker|null $permissionChecker
     * @param ToolInterface[] $tools
     */
    public function __construct(
        private readonly ?PermissionChecker $permissionChecker = null,
        array $tools = []
    ) {
        $this->tools = $tools;
    }

    /**
     * Get all registered tools (regardless of enabled state)
     *
     * @return ToolInterface[]
     */
    public function getAllTools(): array
    {
        return $this->tools;
    }

    /**
     * Get a tool by name (from all registered tools)
     */
    public function getToolByName(string $name): ?ToolInterface
    {
        foreach ($this->tools as $tool) {
            if ($tool->getName() === $name) {
                return $tool;
            }
        }
        return null;
    }

    /**
     * Get all enabled tools (filtered by DB permission check for a given admin user)
     *
     * @return ToolInterface[]
     */
    public function getEnabledTools(?int $adminUserId = null): array
    {
        $enabled = [];
        foreach ($this->tools as $tool) {
            if (!$this->isToolAvailable($tool, $adminUserId)) {
                continue;
            }
            $enabled[$tool->getName()] = $tool;
        }
        return $enabled;
    }

    /**
     * Get tool definitions for AI provider
     *
     * @param int|null $adminUserId
     * @return array<int, array<string, mixed>>
     */
    public function getToolDefinitions(?int $adminUserId = null): array
    {
        $definitions = [];
        foreach ($this->getEnabledTools($adminUserId) as $tool) {
            $schema = $tool->getParameterSchema();
            if ($this->permissionChecker !== null
                && !$this->permissionChecker->isAllowed($adminUserId ?? 0, $tool->getName(), 'write')
            ) {
                $schema = $this->filterSchemaToReadActions($tool, $schema);
            }
            $definitions[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'parameters' => $schema,
            ];
        }
        return $definitions;
    }

    /**
     * Get a tool by name (from enabled tools only)
     *
     * @param string $name
     * @param int|null $adminUserId
     * @return ToolInterface|null
     */
    public function getTool(string $name, ?int $adminUserId = null): ?ToolInterface
    {
        $tool = $this->getToolByName($name);
        if ($tool === null || !$this->isToolAvailable($tool, $adminUserId)) {
            return null;
        }
        return $tool;
    }

    /**
     * Whether a specific invocation (tool + input) is allowed for the admin user.
     * A missing user id routes through the ACL fallback instead of allowing everything.
     *
     * @param ToolInterface $tool
     * @param array<string, mixed> $input
     * @param int|null $adminUserId
     * @return bool
     */
    public function isCallAllowed(ToolInterface $tool, array $input, ?int $adminUserId): bool
    {
        if ($this->permissionChecker === null) {
            return true;
        }
        $action = $tool->isReadOnlyAction($input) ? 'read' : 'write';
        return $this->permissionChecker->isAllowed($adminUserId ?? 0, $tool->getName(), $action);
    }

    private function isToolAvailable(ToolInterface $tool, ?int $adminUserId): bool
    {
        if ($this->permissionChecker === null) {
            return true;
        }
        $action = $this->supportsReadAction($tool) ? 'read' : 'write';
        return $this->permissionChecker->isAllowed($adminUserId ?? 0, $tool->getName(), $action);
    }

    /**
     * Whether the tool can be invoked without write permission (fully read-only or mixed skill)
     */
    private function supportsReadAction(ToolInterface $tool): bool
    {
        if ($tool->isReadOnly()) {
            return true;
        }
        foreach ($this->getActionNames($tool) as $actionName) {
            if ($tool->isReadOnlyAction(['action' => $actionName])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Restrict a mixed skill's action enum to its read-only actions
     *
     * @param ToolInterface $tool
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function filterSchemaToReadActions(ToolInterface $tool, array $schema): array
    {
        if (!isset($schema['properties']['action']['enum'])) {
            return $schema;
        }
        $readActions = [];
        foreach ($this->getActionNames($tool) as $actionName) {
            if ($tool->isReadOnlyAction(['action' => $actionName])) {
                $readActions[] = $actionName;
            }
        }
        if ($readActions !== []) {
            $schema['properties']['action']['enum'] = $readActions;
        }
        return $schema;
    }

    /**
     * @param ToolInterface $tool
     * @return string[]
     */
    private function getActionNames(ToolInterface $tool): array
    {
        $enum = $tool->getParameterSchema()['properties']['action']['enum'] ?? [];
        return is_array($enum) ? $enum : [];
    }
}
