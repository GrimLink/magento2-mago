<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Ui\DataProvider;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\Search\SearchResultFactory;
use Magento\Framework\Api\Search\DocumentFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\App\RequestInterface;

class ConversationsDataProvider extends DataProvider
{
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        private readonly ResourceConnection $resourceConnection,
        private readonly SearchResultFactory $searchResultFactory,
        private readonly DocumentFactory $documentFactory,
        private readonly AttributeValueFactory $attributeValueFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
    }

    public function getSearchResult(): SearchResultInterface
    {
        $connection = $this->resourceConnection->getConnection();
        $conversationTable = $this->resourceConnection->getTableName('maggy_conversation');
        $messageTable = $this->resourceConnection->getTableName('maggy_message');
        $adminUserTable = $this->resourceConnection->getTableName('admin_user');

        $usageTable = $this->resourceConnection->getTableName('maggy_usage_log');

        $select = $connection->select()
            ->from(['c' => $conversationTable], [
                'entity_id' => 'c.entity_id',
                'admin_user_id' => 'c.admin_user_id',
                'title' => 'c.title',
                'created_at' => 'c.created_at',
                'updated_at' => 'c.updated_at',
            ])
            ->joinLeft(
                ['u' => $adminUserTable],
                'c.admin_user_id = u.user_id',
                ['admin_username' => 'u.firstname']
            )
            ->columns([
                'message_count' => new \Zend_Db_Expr(
                    '(SELECT COUNT(*) FROM ' . $messageTable . ' WHERE conversation_id = c.entity_id)'
                ),
                'total_tokens' => new \Zend_Db_Expr(
                    '(SELECT COALESCE(SUM(total_tokens), 0) FROM ' . $usageTable . ' WHERE conversation_id = c.entity_id)'
                ),
                'model' => new \Zend_Db_Expr(
                    '(SELECT MAX(model) FROM ' . $usageTable . ' WHERE conversation_id = c.entity_id)'
                ),
                'estimated_cost' => new \Zend_Db_Expr(
                    '(SELECT ROUND(COALESCE(SUM(input_tokens), 0) / 1000000 * 3.0 + COALESCE(SUM(output_tokens), 0) / 1000000 * 15.0, 4) FROM ' . $usageTable . ' WHERE conversation_id = c.entity_id)'
                ),
                'skills_used' => new \Zend_Db_Expr(
                    '(SELECT GROUP_CONCAT(DISTINCT skill_names) FROM ' . $usageTable . ' WHERE conversation_id = c.entity_id AND skill_names IS NOT NULL AND skill_names != \'\')'
                ),
            ])
            ->order('c.updated_at DESC');

        $rows = $connection->fetchAll($select);

        $documents = [];
        foreach ($rows as $row) {
            $doc = $this->documentFactory->create();
            $doc->setId($row['entity_id']);
            $attributes = [];
            foreach ($row as $key => $value) {
                $attr = $this->attributeValueFactory->create();
                $attr->setAttributeCode($key);
                $attr->setValue($value);
                $attributes[$key] = $attr;
            }
            $doc->setCustomAttributes($attributes);
            $documents[] = $doc;
        }

        $searchResult = $this->searchResultFactory->create();
        $searchResult->setItems($documents);
        $searchResult->setTotalCount(count($documents));
        $searchResult->setSearchCriteria($this->getSearchCriteria());

        return $searchResult;
    }

    public function getData(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $conversationTable = $this->resourceConnection->getTableName('maggy_conversation');
        $messageTable = $this->resourceConnection->getTableName('maggy_message');
        $adminUserTable = $this->resourceConnection->getTableName('admin_user');

        $usageTable = $this->resourceConnection->getTableName('maggy_usage_log');

        $select = $connection->select()
            ->from(['c' => $conversationTable], [
                'entity_id' => 'c.entity_id',
                'admin_user_id' => 'c.admin_user_id',
                'title' => 'c.title',
                'created_at' => 'c.created_at',
                'updated_at' => 'c.updated_at',
            ])
            ->joinLeft(
                ['u' => $adminUserTable],
                'c.admin_user_id = u.user_id',
                ['admin_username' => 'u.firstname']
            )
            ->columns([
                'message_count' => new \Zend_Db_Expr(
                    '(SELECT COUNT(*) FROM ' . $messageTable . ' WHERE conversation_id = c.entity_id)'
                ),
                'total_tokens' => new \Zend_Db_Expr(
                    '(SELECT COALESCE(SUM(total_tokens), 0) FROM ' . $usageTable . ' WHERE conversation_id = c.entity_id)'
                ),
                'model' => new \Zend_Db_Expr(
                    '(SELECT MAX(model) FROM ' . $usageTable . ' WHERE conversation_id = c.entity_id)'
                ),
                'estimated_cost' => new \Zend_Db_Expr(
                    '(SELECT ROUND(COALESCE(SUM(input_tokens), 0) / 1000000 * 3.0 + COALESCE(SUM(output_tokens), 0) / 1000000 * 15.0, 4) FROM ' . $usageTable . ' WHERE conversation_id = c.entity_id)'
                ),
                'skills_used' => new \Zend_Db_Expr(
                    '(SELECT GROUP_CONCAT(DISTINCT skill_names) FROM ' . $usageTable . ' WHERE conversation_id = c.entity_id AND skill_names IS NOT NULL AND skill_names != \'\')'
                ),
            ])
            ->order('c.updated_at DESC');

        $rows = $connection->fetchAll($select);

        return [
            'totalRecords' => count($rows),
            'items' => $rows,
        ];
    }
}
