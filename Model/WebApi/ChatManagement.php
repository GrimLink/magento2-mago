<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Model\WebApi;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Serialize\Serializer\Json;
use MaggyAssistant\Base\Api\ChatServiceInterface;
use MaggyAssistant\Base\Api\ConversationRepositoryInterface;
use MaggyAssistant\Base\Api\WebApi\ChatManagementInterface;
use MaggyAssistant\Base\Logger\ErrorLogger;
use MaggyAssistant\Base\Service\Ai\ChatService;

class ChatManagement implements ChatManagementInterface
{
    public function __construct(
        private readonly ChatServiceInterface $chatService,
        private readonly ConversationRepositoryInterface $conversationRepository,
        private readonly UserContextInterface $userContext,
        private readonly Json $json,
        private readonly ErrorLogger $errorLogger
    ) {
    }

    public function sendMessage(string $message, ?int $conversationId = null): string
    {
        try {
            $adminUserId = (int)$this->userContext->getUserId();
            if (!$adminUserId) {
                return $this->json->serialize(['error' => 'Not authorized']);
            }

            if (!$conversationId) {
                $title = mb_substr($message, 0, 50);
                $conversationId = $this->conversationRepository->create($adminUserId, $title);
            } else {
                // Reject posting into another admin's conversation
                $this->conversationRepository->getByIdForUser($conversationId, $adminUserId);
            }

            $this->conversationRepository->addMessage($conversationId, 'user', $message);

            $messages = $this->conversationRepository->getMessages($conversationId);
            $formattedMessages = $this->formatMessagesForAi($messages);

            $response = $this->chatService->processMessage($formattedMessages, $conversationId, $adminUserId);

            $pendingConfirmation = !empty($response['pending_confirmation']);
            $messageId = $this->conversationRepository->addMessage(
                $conversationId,
                'assistant',
                $response['content'] ?? '',
                $response['tool_calls'] ?? null,
                $pendingConfirmation
            );

            return $this->json->serialize([
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'content' => $response['content'] ?? '',
                'tool_calls' => $response['tool_calls'] ?? [],
                'pending_confirmation' => $pendingConfirmation,
            ]);
        } catch (\Throwable $e) {
            $this->errorLogger->addLog('ChatManagement::sendMessage', $e->getMessage());
            return $this->json->serialize(['error' => $e->getMessage()]);
        }
    }

    public function getConversations(): string
    {
        try {
            $adminUserId = (int)$this->userContext->getUserId();
            $conversations = $this->conversationRepository->getListByUser($adminUserId);
            return $this->json->serialize(['conversations' => $conversations]);
        } catch (\Throwable $e) {
            return $this->json->serialize(['error' => $e->getMessage()]);
        }
    }

    public function getConversation(int $conversationId): string
    {
        try {
            $adminUserId = (int)$this->userContext->getUserId();
            if (!$adminUserId) {
                return $this->json->serialize(['error' => 'Not authorized']);
            }
            $conversation = $this->conversationRepository->getByIdForUser($conversationId, $adminUserId);
            $conversation['messages'] = $this->conversationRepository->getMessages($conversationId);
            return $this->json->serialize($conversation);
        } catch (\Throwable $e) {
            return $this->json->serialize(['error' => $e->getMessage()]);
        }
    }

    public function deleteConversation(int $conversationId): string
    {
        try {
            $adminUserId = (int)$this->userContext->getUserId();
            if (!$adminUserId) {
                return $this->json->serialize(['error' => 'Not authorized']);
            }
            $this->conversationRepository->delete($conversationId, $adminUserId);
            return $this->json->serialize(['success' => true]);
        } catch (\Throwable $e) {
            return $this->json->serialize(['error' => $e->getMessage()]);
        }
    }

    public function confirmAction(int $messageId): string
    {
        try {
            $adminUserId = (int)$this->userContext->getUserId();
            if (!$adminUserId) {
                return $this->json->serialize(['error' => 'Not authorized']);
            }
            $message = $this->conversationRepository->getMessageForUser($messageId, $adminUserId);
            if (empty($message['pending_confirmation'])) {
                return $this->json->serialize(['error' => 'No pending confirmation for this message']);
            }

            $toolCalls = $message['tool_calls'] ?? [];
            if (is_string($toolCalls)) {
                $toolCalls = $this->json->unserialize($toolCalls);
            }

            /** @var ChatService $chatService */
            $chatService = $this->chatService;
            $results = $chatService->executeConfirmedTools($toolCalls, $adminUserId);

            $this->conversationRepository->resolveConfirmation($messageId, true, $adminUserId);

            // Add tool results as messages and continue conversation
            $conversationId = (int)$message['conversation_id'];
            foreach ($results as $toolCallId => $result) {
                $this->conversationRepository->addMessage(
                    $conversationId,
                    'tool',
                    $this->json->serialize($result)
                );
            }

            // Get follow-up response from AI
            $messages = $this->conversationRepository->getMessages($conversationId);
            $formattedMessages = $this->formatMessagesForAi($messages);
            $response = $this->chatService->processMessage($formattedMessages, $conversationId, $adminUserId);

            $responseMessageId = $this->conversationRepository->addMessage(
                $conversationId,
                'assistant',
                $response['content'] ?? ''
            );

            return $this->json->serialize([
                'success' => true,
                'message_id' => $responseMessageId,
                'content' => $response['content'] ?? '',
                'tool_results' => $results,
            ]);
        } catch (\Throwable $e) {
            $this->errorLogger->addLog('ChatManagement::confirmAction', $e->getMessage());
            return $this->json->serialize(['error' => $e->getMessage()]);
        }
    }

    public function rejectAction(int $messageId): string
    {
        try {
            $adminUserId = (int)$this->userContext->getUserId();
            if (!$adminUserId) {
                return $this->json->serialize(['error' => 'Not authorized']);
            }
            $message = $this->conversationRepository->getMessageForUser($messageId, $adminUserId);
            $this->conversationRepository->resolveConfirmation($messageId, false, $adminUserId);

            $conversationId = (int)$message['conversation_id'];

            $this->conversationRepository->addMessage(
                $conversationId,
                'assistant',
                'The action was rejected by the user. No changes were made.'
            );

            return $this->json->serialize(['success' => true, 'message' => 'Action rejected']);
        } catch (\Throwable $e) {
            return $this->json->serialize(['error' => $e->getMessage()]);
        }
    }

    private function formatMessagesForAi(array $messages): array
    {
        $formatted = [];
        foreach ($messages as $msg) {
            $entry = [
                'role' => $msg['role'],
                'content' => $msg['content'] ?? '',
            ];

            if (!empty($msg['tool_calls'])) {
                $toolCalls = $msg['tool_calls'];
                if (is_string($toolCalls)) {
                    try {
                        $toolCalls = json_decode($toolCalls, true, 512, JSON_THROW_ON_ERROR);
                    } catch (\Throwable $e) {
                        $toolCalls = [];
                    }
                }
                $entry['tool_calls'] = $toolCalls;
            }

            $formatted[] = $entry;
        }
        return $formatted;
    }
}
