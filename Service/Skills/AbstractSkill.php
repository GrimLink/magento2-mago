<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Skills;

use Magento\Framework\AuthorizationInterface;
use MaggyAssistant\Base\Api\Skill\ActionInterface;
use MaggyAssistant\Base\Api\Tool\ToolInterface;

abstract class AbstractSkill implements ToolInterface
{
    /** @var ActionInterface[] */
    private readonly array $actions;

    /**
     * @param ActionInterface[] $actions
     */
    public function __construct(
        private readonly AuthorizationInterface $authorization,
        array $actions = []
    ) {
        $this->actions = $actions;
    }

    abstract public function getName(): string;

    abstract protected function getBaseDescription(): string;

    public function getDescription(): string
    {
        $parts = [];
        foreach ($this->actions as $action) {
            $parts[] = '"' . $action->getName() . '" (' . $action->getDescription() . ')';
        }

        return $this->getBaseDescription() . ' Actions: ' . implode(', ', $parts) . '.';
    }

    public function getParameterSchema(): array
    {
        $actionNames = [];
        $properties = [];

        foreach ($this->actions as $action) {
            $actionNames[] = $action->getName();
            foreach ($action->getParameterSchema() as $paramName => $paramSchema) {
                if (!isset($properties[$paramName])) {
                    $properties[$paramName] = $paramSchema;
                }
            }
        }

        $properties = array_merge(
            [
                'action' => [
                    'type' => 'string',
                    'enum' => $actionNames,
                    'description' => 'The action to perform',
                ],
            ],
            $properties
        );

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => ['action'],
        ];
    }

    public function execute(array $params): array
    {
        $actionName = $params['action'] ?? '';
        $action = $this->actions[$actionName] ?? null;

        if (!$action) {
            return ['error' => 'Unknown action: ' . $actionName];
        }

        $acl = $action->getAclResource();
        if ($acl && !$this->authorization->isAllowed($acl)) {
            return ['error' => 'You do not have permission to access this data'];
        }

        return $action->execute($params, (int)($params['_admin_user_id'] ?? 0));
    }

    public function isReadOnly(): bool
    {
        foreach ($this->actions as $action) {
            if (!$action->isReadOnly()) {
                return false;
            }
        }

        return true;
    }

    public function isReadOnlyAction(array $input): bool
    {
        $actionName = $input['action'] ?? '';
        if ($actionName && isset($this->actions[$actionName])) {
            return $this->actions[$actionName]->isReadOnly();
        }

        return $this->isReadOnly();
    }

    public function getRequiredAcl(): string
    {
        return 'MaggyAssistant_Base::assistant_read';
    }

    public function getInstructions(): string
    {
        $parts = [];
        $base = $this->getBaseInstructions();
        if ($base) {
            $parts[] = $base;
        }
        foreach ($this->actions as $action) {
            $inst = $action->getInstructions();
            if ($inst) {
                $parts[] = '## ' . $action->getName() . "\n" . $inst;
            }
        }
        return implode("\n\n", $parts);
    }

    public function getMagentoAcl(): string
    {
        return '';
    }

    protected function getBaseInstructions(): string
    {
        return '';
    }
}
