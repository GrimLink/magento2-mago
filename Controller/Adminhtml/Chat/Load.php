<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Controller\Adminhtml\Chat;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use MagoAssistant\Mago\Api\ConversationRepositoryInterface;

class Load extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MagoAssistant_Mago::assistant_read';

    public function __construct(
        Context $context,
        private readonly ConversationRepositoryInterface $conversationRepository,
        private readonly JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        try {
            $conversationId = (int)$this->getRequest()->getParam('conversation_id', 0);

            if (!$conversationId) {
                return $result->setData(['error' => 'Conversation ID is required']);
            }

            $user = $this->_auth->getUser();
            $adminUserId = $user ? (int)$user->getId() : 0;
            if (!$adminUserId) {
                return $result->setData(['error' => 'Not authorized']);
            }

            $conversation = $this->conversationRepository->getByIdForUser($conversationId, $adminUserId);
            $messages = $this->conversationRepository->getMessages($conversationId);

            return $result->setData([
                'entity_id' => $conversation['entity_id'],
                'title' => $conversation['title'],
                'messages' => $messages,
            ]);
        } catch (\Throwable $e) {
            return $result->setData(['error' => $e->getMessage()]);
        }
    }
}
