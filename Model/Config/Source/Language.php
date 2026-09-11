<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Language implements OptionSourceInterface
{
    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'auto', 'label' => __('Auto-detect (match user language)')],
            ['value' => 'English', 'label' => __('English')],
            ['value' => 'Dutch', 'label' => __('Nederlands (Dutch)')],
            ['value' => 'German', 'label' => __('Deutsch (German)')],
            ['value' => 'French', 'label' => __('Français (French)')],
            ['value' => 'Spanish', 'label' => __('Español (Spanish)')],
            ['value' => 'Italian', 'label' => __('Italiano (Italian)')],
            ['value' => 'Portuguese', 'label' => __('Português (Portuguese)')],
        ];
    }
}
