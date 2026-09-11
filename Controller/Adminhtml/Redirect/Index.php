<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Controller\Adminhtml\Redirect;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\Controller\Result\RedirectFactory;

/**
 * Legacy redirect controller — kept for backwards compatibility with old links.
 */
class Index extends Action
{
    public const ADMIN_RESOURCE = 'MagoAssistant_Mago::assistant_read';

    public function __construct(
        Context $context,
        private readonly BackendUrl $backendUrl,
        private readonly RedirectFactory $redirectFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $route = (string)$this->getRequest()->getParam('r');
        if (!$route) {
            return $this->redirectFactory->create()->setUrl(
                $this->backendUrl->getUrl('adminhtml/dashboard')
            );
        }

        $params = [];
        foreach ($this->getRequest()->getParams() as $k => $v) {
            if (!in_array($k, ['r', 's', 'key'], true)) {
                $params[$k] = (string)$v;
            }
        }

        return $this->redirectFactory->create()->setUrl(
            $this->backendUrl->getUrl($route, $params)
        );
    }

    public function _processUrlKeys(): bool
    {
        return true;
    }
}
