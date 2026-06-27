<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Controller\Adminhtml\Conversations;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\ResultInterface;
use Magento\Ui\Component\MassAction\Filter;
use MaggyAssistant\Base\Ui\DataProvider\ConversationsDataProvider;

class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MaggyAssistant_Base::conversations_delete';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly ResourceConnection $resourceConnection,
        private readonly ConversationsDataProvider $dataProvider
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $collection = $this->filter->getComponent()->getContext()->getDataProvider();
        $selected = $this->getRequest()->getParam('selected', []);
        $excluded = $this->getRequest()->getParam('excluded', []);

        $connection = $this->resourceConnection->getConnection();
        $conversationTable = $this->resourceConnection->getTableName('maggy_conversation');
        $messageTable = $this->resourceConnection->getTableName('maggy_message');
        $usageTable = $this->resourceConnection->getTableName('maggy_usage_log');

        if ($excluded === 'false' || $excluded === false) {
            // "Select All" was used — get all IDs from data provider, minus excluded
            $ids = $selected;
        } elseif (!empty($selected)) {
            $ids = $selected;
        } else {
            $this->messageManager->addErrorMessage(__('No conversations selected.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        $ids = array_map('intval', $ids);
        if (empty($ids)) {
            $this->messageManager->addErrorMessage(__('No conversations selected.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        try {
            $connection->delete($usageTable, ['conversation_id IN (?)' => $ids]);
            $connection->delete($messageTable, ['conversation_id IN (?)' => $ids]);
            $deleted = $connection->delete($conversationTable, ['entity_id IN (?)' => $ids]);

            $this->messageManager->addSuccessMessage(
                __('A total of %1 conversation(s) have been deleted.', $deleted)
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('An error occurred while deleting conversations: %1', $e->getMessage())
            );
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
