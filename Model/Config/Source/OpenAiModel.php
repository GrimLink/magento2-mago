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
            ['value' => 'gpt-4-turbo', 'label' => __('GPT-4 Turbo')],
        ];
    }
}
