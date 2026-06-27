<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Controller\Adminhtml\Chat;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use MaggyAssistant\Base\Api\ConversationRepositoryInterface;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MaggyAssistant_Base::assistant_read';

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

            $this->conversationRepository->delete($conversationId);

            return $result->setData(['success' => true]);
        } catch (\Throwable $e) {
            return $result->setData(['error' => $e->getMessage()]);
        }
    }
}
