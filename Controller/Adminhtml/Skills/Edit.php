<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Controller\Adminhtml\Skills;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use MaggyAssistant\Base\Service\Tool\ToolRegistry;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MaggyAssistant_Base::config';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly ToolRegistry $toolRegistry
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $skillName = $this->getRequest()->getParam('skill_name');
        $tool = $skillName ? $this->toolRegistry->getToolByName($skillName) : null;

        if (!$tool) {
            $this->messageManager->addErrorMessage(__('Skill not found.'));
            return $this->resultRedirectFactory->create()->setPath('maggy/skills/index');
        }

        $resultPage = $this->pageFactory->create();
        $resultPage->setActiveMenu('MaggyAssistant_Base::skills');
        $resultPage->getConfig()->getTitle()->prepend(__('Edit Skill: %1', $tool->getName()));
        return $resultPage;
    }
}
