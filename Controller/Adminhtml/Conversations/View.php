<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Controller\Adminhtml\Conversations;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class View extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MaggyAssistant_Base::config';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly ResourceConnection $resourceConnection
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $conversationId = (int)$this->getRequest()->getParam('id');
        if (!$conversationId) {
            $this->messageManager->addErrorMessage(__('Conversation not found.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        $connection = $this->resourceConnection->getConnection();
        $title = $connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName('maggy_conversation'), ['title'])
                ->where('entity_id = ?', $conversationId)
        );

        $resultPage = $this->pageFactory->create();
        $resultPage->setActiveMenu('MaggyAssistant_Base::conversations');
        $resultPage->getConfig()->getTitle()->prepend($title ?: __('Conversation #%1', $conversationId));
        return $resultPage;
    }
}
