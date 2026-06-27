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

class Delete extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MaggyAssistant_Base::conversations_delete';

    public function __construct(
        Context $context,
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

        try {
            $connection = $this->resourceConnection->getConnection();
            $connection->delete(
                $this->resourceConnection->getTableName('maggy_usage_log'),
                ['conversation_id = ?' => $conversationId]
            );
            $connection->delete(
                $this->resourceConnection->getTableName('maggy_message'),
                ['conversation_id = ?' => $conversationId]
            );
            $connection->delete(
                $this->resourceConnection->getTableName('maggy_conversation'),
                ['entity_id = ?' => $conversationId]
            );

            $this->messageManager->addSuccessMessage(__('Conversation has been deleted.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('An error occurred while deleting the conversation: %1', $e->getMessage())
            );
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
