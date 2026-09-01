<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use MaggyAssistant\Base\Api\Config\RepositoryInterface as ConfigRepository;

class VersionField extends Field
{
    public function __construct(
        Context $context,
        private readonly ConfigRepository $configRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        return '<strong>' . $this->escapeHtml($this->configRepository->getExtensionVersion()) . '</strong>';
    }
}
