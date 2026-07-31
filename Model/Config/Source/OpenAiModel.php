<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class OpenAiModel implements OptionSourceInterface
{
    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'gpt-4o', 'label' => __('GPT-4o')],
            ['value' => 'gpt-4o-mini', 'label' => __('GPT-4o Mini')],
            ['value' => 'gpt-4.1', 'label' => __('GPT-4.1')],
            ['value' => 'gpt-4.1-mini', 'label' => __('GPT-4.1 Mini')],
            ['value' => 'gpt-4.1-nano', 'label' => __('GPT-4.1 Nano')],
            ['value' => 'o3-mini', 'label' => __('o3-mini')],
        ];
    }
}
