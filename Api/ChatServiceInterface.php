<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Api;

/**
 * Chat service interface — orchestrates AI + tools
 * @api
 */
interface ChatServiceInterface
{
    /**
     * Process a user message and return the AI response
     *
     * @param array $messages Conversation history
     * @param int|null $conversationId
     * @param int|null $adminUserId
     * @return array Response with 'content', 'tool_calls', 'pending_confirmation' keys
     */
    public function processMessage(array $messages, ?int $conversationId = null, ?int $adminUserId = null): array;

    /**
     * Process a user message with streaming
     *
     * @param array $messages
     * @param callable $onChunk fn(string $type, array $data)
     * @param int|null $conversationId
     * @param int|null $adminUserId
     * @return array
     */
    public function processMessageStreaming(array $messages, callable $onChunk, ?int $conversationId = null, ?int $adminUserId = null): array;

    /**
     * Execute confirmed write tool calls
     *
     * @param array $toolCalls
     * @param int|null $adminUserId
     * @return array Results keyed by tool call ID
     */
    public function executeConfirmedTools(array $toolCalls, ?int $adminUserId = null): array;
}
