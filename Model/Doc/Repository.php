<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Model\Doc;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression;

class Repository
{
    private const TABLE = 'maggy_doc';
    private const MAX_SEARCH_LIMIT = 20;

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

        $snippetExpr = 'SUBSTRING(content, 1, 400)';
        $anchor = $this->longestWord($query);
        if ($anchor !== '') {
            $anchorQuoted = $connection->quote($anchor);
            $snippetExpr = "SUBSTRING(content, GREATEST(1, LOCATE({$anchorQuoted}, content) - 120), 400)";
        }

        $select = $connection->select()
            ->from($table, [
                'entity_id',
                'title',
                'description',
                'url',
                'edition',
                'snippet' => new Expression($snippetExpr),
                'relevance' => new Expression($matchExpr),
            ])
            ->where($matchExpr)
            ->order('relevance DESC')
            ->limit(min(self::MAX_SEARCH_LIMIT, max(1, $limit)));

        return $connection->fetchAll($select);
    }

    private function longestWord(string $query): string
    {
        if (!preg_match_all('/\w{3,}/u', $query, $matches)) {
            return '';
        }
        usort($matches[0], static fn(string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $matches[0][0];
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
     * Replace the whole corpus: truncate + bulk insert with the fulltext index temporarily
     * disabled to avoid InnoDB deadlocks on the FTS auxiliary tables.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function replaceAll(array $rows): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);

        $connection->query("ALTER TABLE {$table} DISABLE KEYS");
        try {
            $connection->truncateTable($table);
            foreach (array_chunk($rows, 50) as $chunk) {
                if ($chunk) {
                    $connection->insertMultiple($table, $chunk);
                }
            }
        } finally {
            $connection->query("ALTER TABLE {$table} ENABLE KEYS");
        }
    }
}
