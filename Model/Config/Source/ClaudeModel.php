<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ClaudeModel implements OptionSourceInterface
{
    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'claude-sonnet-4-20250514', 'label' => __('Claude Sonnet 4')],
            ['value' => 'claude-opus-4-20250514', 'label' => __('Claude Opus 4')],
            ['value' => 'claude-haiku-4-20250514', 'label' => __('Claude Haiku 4')],
        ];
    }
}
