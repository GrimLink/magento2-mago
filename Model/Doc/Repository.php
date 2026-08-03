<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Model\Doc;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression;

/**
 * Data access for the documentation corpus (maggy_doc).
 */
class Repository
{
    private const TABLE = 'maggy_doc';

    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Full-text search over the corpus. Returns id/title/description/url/edition + a short snippet,
     * ordered by relevance.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $limit): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        // Quote the query into the expression to avoid named-parameter reuse issues.
        $quoted = $connection->quote($query);
        $matchExpr = "MATCH(title, description, tags, content) AGAINST ({$quoted} IN NATURAL LANGUAGE MODE)";

        $select = $connection->select()
            ->from($table, [
                'entity_id',
                'title',
                'description',
                'url',
                'edition',
                'snippet' => new Expression('SUBSTRING(content, 1, 400)'),
                'relevance' => new Expression($matchExpr),
            ])
            ->where($matchExpr)
            ->order('relevance DESC')
            ->limit(max(1, $limit));

        return $connection->fetchAll($select);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $select = $connection->select()->from($table)->where('entity_id = ?', $id);
        $row = $connection->fetchRow($select);

        return $row ?: null;
    }

    public function count(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $select = $connection->select()->from($table, new Expression('COUNT(*)'));

        return (int)$connection->fetchOne($select);
    }

    /**
     * Atomically replace the whole corpus (delete all + bulk insert) in one transaction,
     * so retrieval never sees an empty table mid-request.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function replaceAll(array $rows): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $connection->beginTransaction();
        try {
            $connection->delete($table);
            foreach (array_chunk($rows, 50) as $chunk) {
                if ($chunk) {
                    $connection->insertMultiple($table, $chunk);
                }
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }
}
