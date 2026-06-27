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
            if ($adminUserId !== null && $this->permissionChecker !== null) {
                $action = $tool->isReadOnly() ? 'read' : 'write';
                if (!$this->permissionChecker->isAllowed($adminUserId, $tool->getName(), $action)) {
                    continue;
                }
            }
            $enabled[$tool->getName()] = $tool;
        }
        return $enabled;
    }

    /**
     * Get tool definitions for AI provider
     *
     * @return array
     */
    public function getToolDefinitions(): array
    {
        $definitions = [];
        foreach ($this->getEnabledTools() as $tool) {
            $definitions[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'parameters' => $tool->getParameterSchema(),
            ];
        }
        return $definitions;
    }

    /**
     * Get a tool by name (from enabled tools only)
     *
     * @param string $name
     * @return ToolInterface|null
     */
    public function getTool(string $name): ?ToolInterface
    {
        $enabled = $this->getEnabledTools();
        return $enabled[$name] ?? null;
    }
}
