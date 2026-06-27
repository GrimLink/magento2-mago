<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Controller\Adminhtml\Conversations;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * Redirects to the dashboard and opens the chat panel with the given conversation loaded.
 * Only allows the conversation owner to continue.
 */
class ContinueChat extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'MaggyAssistant_Base::config';

    public function __construct(
        Context $context,
        private readonly ResourceConnection $resourceConnection,
        private readonly AdminSession $adminSession
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $conversationId = (int)$this->getRequest()->getParam('id');
        if (!$conversationId) {
            $this->messageManager->addErrorMessage(__('Conversation not found.'));
            return $this->resultRedirectFactory->create()->setPath('maggy/conversations/index');
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('maggy_conversation');
        $ownerId = (int)$connection->fetchOne(
            $connection->select()->from($table, ['admin_user_id'])->where('entity_id = ?', $conversationId)
        );

        $currentUserId = (int)$this->adminSession->getUser()?->getId();
        if ($ownerId !== $currentUserId) {
            $this->messageManager->addErrorMessage(__('You can only continue your own conversations.'));
            return $this->resultRedirectFactory->create()->setPath(
                'maggy/conversations/view',
                ['id' => $conversationId]
            );
        }

        /** @var \Magento\Framework\Controller\Result\Raw $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $dashboardUrl = $this->getUrl('adminhtml/dashboard/index');
        $result->setContents(
            '<html><body><script>'
            . 'try{sessionStorage.setItem("maggy_open","1");'
            . 'sessionStorage.setItem("maggy_conv","' . $conversationId . '");'
            . '}catch(e){}'
            . 'window.location.href="' . $dashboardUrl . '";'
            . '</script></body></html>'
        );
        return $result;
    }
}
