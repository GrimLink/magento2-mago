<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use MagoAssistant\Mago\Service\Tool\ToolRegistry;

class Skills extends Template
{
    public function __construct(
        Context $context,
        private readonly ToolRegistry $toolRegistry,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getSkills(): array
    {
        $skills = [];
        foreach ($this->toolRegistry->getAllTools() as $tool) {
            $className = get_class($tool);
            $parts = explode('\\', $className);
            $category = $parts[3] ?? '';

            $skills[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'is_read_only' => $tool->isReadOnly(),
                'category' => $category,
            ];
        }
        return $skills;
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('mago/skills/savePermissions');
    }
}
