<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Controller\Adminhtml\Chat;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Serialize\Serializer\Json;
use MaggyAssistant\Base\Api\ConversationRepositoryInterface;
use MaggyAssistant\Base\Logger\ErrorLogger;

class Reject extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public const ADMIN_RESOURCE = 'MaggyAssistant_Base::assistant_write';

    public function __construct(
        Context $context,
        private readonly ConversationRepositoryInterface $conversationRepository,
        private readonly JsonFactory $jsonFactory,
        private readonly Json $json,
        private readonly ErrorLogger $errorLogger
    ) {
        parent::__construct($context);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function _processUrlKeys(): bool
    {
        return true;
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

            $this->conversationRepository->resolveConfirmation($messageId, false);

            $message = $this->conversationRepository->getMessageById($messageId);
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
