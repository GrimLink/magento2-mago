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
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Serialize\Serializer\Json;
use MagoAssistant\Mago\Api\ConversationRepositoryInterface;
use MagoAssistant\Mago\Logger\ErrorLogger;

class Reject extends Action implements HttpPostActionInterface
{
    use FormKeyJsonValidation;

    public const ADMIN_RESOURCE = 'MagoAssistant_Mago::assistant_write';

    public function __construct(
        Context $context,
        private readonly ConversationRepositoryInterface $conversationRepository,
        private readonly JsonFactory $jsonFactory,
        private readonly Json $json,
        private readonly ErrorLogger $errorLogger,
        private readonly FormKey $formKey
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        try {
            $rawBody = $this->getRequest()->getContent();
            $postData = $this->json->unserialize($rawBody);
            $messageId = (int)($postData['message_id'] ?? 0);

            if (!$messageId) {
                return $result->setData(['error' => 'message_id is required']);
            }

            $user = $this->_auth->getUser();
            $adminUserId = $user ? (int)$user->getId() : 0;
            if (!$adminUserId) {
                return $result->setData(['error' => 'Not authorized']);
            }

            $message = $this->conversationRepository->getMessageForUser($messageId, $adminUserId);
            $this->conversationRepository->resolveConfirmation($messageId, false, $adminUserId);

            $conversationId = (int)$message['conversation_id'];

            $this->conversationRepository->addMessage(
                $conversationId,
                'assistant',
                'The action was rejected by the user. No changes were made.'
            );

            return $result->setData(['success' => true]);
        } catch (\Throwable $e) {
            $this->errorLogger->addLog('Reject Controller', $e->getMessage());
            return $result->setData(['error' => $e->getMessage()]);
        }
    }
}
