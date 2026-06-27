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
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Serialize\Serializer\Json;
use MaggyAssistant\Base\Api\ChatServiceInterface;
use MaggyAssistant\Base\Api\ConversationRepositoryInterface;
use MaggyAssistant\Base\Logger\ErrorLogger;

class Confirm extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public const ADMIN_RESOURCE = 'MaggyAssistant_Base::assistant_write';

    public function __construct(
        Context $context,
        private readonly ChatServiceInterface $chatService,
        private readonly ConversationRepositoryInterface $conversationRepository,
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

    public function execute(): ResultInterface|HttpResponse
    {
        /** @var HttpResponse $response */
        $response = $this->getResponse();
        $response->setHeader('Content-Type', 'text/event-stream', true);
        $response->setHeader('Cache-Control', 'no-cache', true);
        $response->setHeader('Connection', 'keep-alive', true);
        $response->setHeader('X-Accel-Buffering', 'no', true);
        $response->sendHeaders();

        while (ob_get_level()) {
            ob_end_clean();
        }

        try {
            $rawBody = $this->getRequest()->getContent();
            $postData = $this->json->unserialize($rawBody);
            $messageId = (int)($postData['message_id'] ?? 0);

            if (!$messageId) {
                $this->sendSse('error', ['error' => 'message_id is required']);
                $this->sendSse('done', []);
                $this->terminateResponse();
            }

            $message = $this->conversationRepository->getMessageById($messageId);
            if (empty($message['pending_confirmation'])) {
                $this->sendSse('error', ['error' => 'No pending confirmation for this message']);
                $this->sendSse('done', []);
                $this->terminateResponse();
            }

            $toolCalls = $message['tool_calls'] ?? [];
            if (is_string($toolCalls)) {
                $toolCalls = $this->json->unserialize($toolCalls);
            }

            $user = $this->_auth->getUser();
            $adminUserId = $user ? (int)$user->getId() : 0;

            $results = $this->chatService->executeConfirmedTools($toolCalls, $adminUserId);
            $this->conversationRepository->resolveConfirmation($messageId, true);

            $conversationId = (int)$message['conversation_id'];
            foreach ($results as $toolCallId => $result) {
                $this->conversationRepository->addMessage(
                    $conversationId,
                    'tool',
                    $this->json->serialize($result),
                    null,
                    false,
                    $toolCallId
                );
            }

            // Stream follow-up AI response
            $messages = $this->conversationRepository->getMessages($conversationId);

            // Collect tool response IDs to validate tool_call chains
            $toolResponseIds = [];
            foreach ($messages as $msg) {
                if (($msg['role'] ?? '') === 'tool' && !empty($msg['tool_call_id'])) {
                    $toolResponseIds[$msg['tool_call_id']] = true;
                }
            }

            $formattedMessages = [];
            foreach ($messages as $msg) {
                $entry = ['role' => $msg['role'], 'content' => $msg['content'] ?? ''];
                if (!empty($msg['tool_calls'])) {
                    $tc = $msg['tool_calls'];
                    if (is_string($tc)) {
                        try {
                            $tc = json_decode($tc, true, 512, JSON_THROW_ON_ERROR);
                        } catch (\Throwable $e) {
                            $tc = [];
                        }
                    }
                    // Only include tool_calls if all responses exist
                    $allResolved = true;
                    foreach ($tc as $call) {
                        if (!isset($toolResponseIds[$call['id'] ?? ''])) {
                            $allResolved = false;
                            break;
                        }
                    }
                    if ($allResolved && !empty($tc)) {
                        $entry['tool_calls'] = $tc;
                    }
                }
                if (!empty($msg['tool_call_id'])) {
                    $entry['tool_call_id'] = $msg['tool_call_id'];
                }
                $formattedMessages[] = $entry;
            }

            $result = $this->chatService->processMessageStreaming(
                $formattedMessages,
                function (string $type, array $data) {
                    $this->sendSse($type, $data);
                },
                $conversationId,
                $adminUserId
            );

            $content = $result['content'] ?? '';
            $this->conversationRepository->addMessage($conversationId, 'assistant', $content);

            $this->sendSse('done', ['conversation_id' => $conversationId], true);
        } catch (\Throwable $e) {
            $this->errorLogger->addLog('Confirm Controller', $e->getMessage());
            $this->sendSse('error', ['error' => $e->getMessage()]);
            $this->sendSse('done', [], true);
        }

        $this->terminateResponse();
    }

    private function sendSse(string $event, array $data, bool $pad = false): void
    {
        $payload = "event: {$event}\ndata: " . json_encode($data) . "\n\n";
        if ($pad) {
            $payload .= str_repeat(": \n", max(0, (int)ceil((4096 - strlen($payload)) / 3)));
        }
        echo $payload;
        flush();
    }

    /**
     * @SuppressWarnings(PHPMD.ExitExpression)
     */
    private function terminateResponse(): never
    {
        exit(0);
    }
}
