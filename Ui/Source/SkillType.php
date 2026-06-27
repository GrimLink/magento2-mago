<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Ui\Source;

use Magento\Framework\Data\OptionSourceInterface;

class SkillType implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'read', 'label' => __('Read')],
            ['value' => 'write', 'label' => __('Write')],
        ];
    }
}
