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

/**
 * Fallback endpoint to check if a conversation has a pending confirmation.
 * Used when SSE done event is lost due to nginx/proxy buffering.
 */
class Status extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public const ADMIN_RESOURCE = 'MaggyAssistant_Base::assistant_read';

    public function __construct(
        Context $context,
        private readonly ConversationRepositoryInterface $conversationRepository,
        private readonly JsonFactory $jsonFactory,
        private readonly Json $json
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
            $conversationId = (int)($postData['conversation_id'] ?? 0);

            if (!$conversationId) {
                return $result->setData(['pending_confirmation' => false]);
            }

            $messages = $this->conversationRepository->getMessages($conversationId);
            $lastAssistant = null;
            foreach (array_reverse($messages) as $msg) {
                if (($msg['role'] ?? '') === 'assistant') {
                    $lastAssistant = $msg;
                    break;
                }
            }

            if ($lastAssistant && !empty($lastAssistant['pending_confirmation'])) {
                return $result->setData([
                    'pending_confirmation' => true,
                    'message_id' => (int)$lastAssistant['entity_id'],
                    'content' => $lastAssistant['content'] ?? '',
                ]);
            }

            return $result->setData(['pending_confirmation' => false]);
        } catch (\Throwable $e) {
            return $result->setData(['pending_confirmation' => false, 'error' => $e->getMessage()]);
        }
    }
}
