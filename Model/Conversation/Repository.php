<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Model\Conversation;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Serialize\Serializer\Json;
use MaggyAssistant\Base\Api\ConversationRepositoryInterface;

class Repository implements ConversationRepositoryInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Json $json
    ) {
    }

    public function create(int $adminUserId, string $title = 'New Chat'): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('maggy_conversation');

        $connection->insert($table, [
            'admin_user_id' => $adminUserId,
            'title' => $title,
        ]);

        return (int)$connection->lastInsertId($table);
    }

    public function getById(int $conversationId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('maggy_conversation');

        $select = $connection->select()->from($table)->where('entity_id = ?', $conversationId);
        $row = $connection->fetchRow($select);

        if (!$row) {
            throw new \InvalidArgumentException('Conversation not found: ' . $conversationId);
        }

        return $row;
    }

    public function getListByUser(int $adminUserId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('maggy_conversation');

        $select = $connection->select()
            ->from($table)
            ->where('admin_user_id = ?', $adminUserId)
            ->order('updated_at DESC');

        return $connection->fetchAll($select);
    }

    public function delete(int $conversationId): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('maggy_conversation');
        $connection->delete($table, ['entity_id = ?' => $conversationId]);
    }

    public function addMessage(
        int $conversationId,
        string $role,
        string $content,
        ?array $toolCalls = null,
        bool $pendingConfirmation = false,
        ?string $toolCallId = null
    ): int {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('maggy_message');

        $data = [
            'conversation_id' => $conversationId,
            'role' => $role,
            'content' => $content,
            'pending_confirmation' => $pendingConfirmation ? 1 : 0,
        ];

        if ($toolCalls !== null) {
            $data['tool_calls'] = $this->json->serialize($toolCalls);
        }

        if ($toolCallId !== null) {
            $data['tool_call_id'] = $toolCallId;
        }

        $connection->insert($table, $data);
        $messageId = (int)$connection->lastInsertId($table);

        // Touch conversation updated_at
        $convTable = $this->resourceConnection->getTableName('maggy_conversation');
        $connection->update($convTable, ['updated_at' => new Expression('NOW()')], ['entity_id = ?' => $conversationId]);

        return $messageId;
    }

    public function getMessages(int $conversationId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('maggy_message');

        $select = $connection->select()
            ->from($table)
            ->where('conversation_id = ?', $conversationId)
            ->order('created_at ASC');

        return $connection->fetchAll($select);
    }

    public function getMessageById(int $messageId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('maggy_message');

        $select = $connection->select()->from($table)->where('entity_id = ?', $messageId);
        $row = $connection->fetchRow($select);

        if (!$row) {
            throw new \InvalidArgumentException('Message not found: ' . $messageId);
        }

        return $row;
    }

    public function resolveConfirmation(int $messageId, bool $confirmed): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('maggy_message');
        $connection->update($table, ['pending_confirmation' => 0], ['entity_id = ?' => $messageId]);
    }
}
