<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Api;

/**
 * Conversation repository interface
 * @api
 */
interface ConversationRepositoryInterface
{
    /**
     * @param int $adminUserId
     * @param string $title
     * @return int Conversation ID
     */
    public function create(int $adminUserId, string $title = 'New Chat'): int;

    /**
     * @param int $conversationId
     * @return array
     */
    public function getById(int $conversationId): array;

    /**
     * @param int $adminUserId
     * @return array
     */
    public function getListByUser(int $adminUserId): array;

    /**
     * @param int $conversationId
     * @return void
     */
    public function delete(int $conversationId): void;

    /**
     * @param int $conversationId
     * @param string $role user|assistant|system|tool
     * @param string $content
     * @param array|null $toolCalls
     * @param bool $pendingConfirmation
     * @return int Message ID
     */
    public function addMessage(
        int $conversationId,
        string $role,
        string $content,
        ?array $toolCalls = null,
        bool $pendingConfirmation = false,
        ?string $toolCallId = null
    ): int;

    /**
     * @param int $conversationId
     * @return array
     */
    public function getMessages(int $conversationId): array;

    /**
     * @param int $messageId
     * @return array
     */
    public function getMessageById(int $messageId): array;

    /**
     * @param int $messageId
     * @param bool $confirmed
     * @return void
     */
    public function resolveConfirmation(int $messageId, bool $confirmed): void;
}
