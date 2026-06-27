<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Provider implements OptionSourceInterface
{
    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'claude', 'label' => __('Claude (Anthropic)')],
            ['value' => 'openai', 'label' => __('OpenAI')],
        ];
    }
}
