<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;
use MaggyAssistant\Base\Api\Tool\ToolInterface;
use MaggyAssistant\Base\Service\Tool\ToolRegistry;

class SkillEdit extends Template
{
    public function __construct(
        Context $context,
        private readonly ToolRegistry $toolRegistry,
        private readonly ResourceConnection $resourceConnection,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getSkill(): ?ToolInterface
    {
        $skillName = $this->getRequest()->getParam('skill_name');
        return $skillName ? $this->toolRegistry->getToolByName($skillName) : null;
    }

    public function getAdminUsers(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $userTable = $this->resourceConnection->getTableName('admin_user');

        $select = $connection->select()
            ->from($userTable, ['user_id', 'username', 'firstname', 'lastname'])
            ->where('is_active = ?', 1)
            ->order('username ASC');

        return $connection->fetchAll($select);
    }

    public function getCurrentPermissions(): array
    {
        $skill = $this->getSkill();
        if (!$skill) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('maggy_skill_permission');

        $select = $connection->select()
            ->from($table, ['admin_user_id', 'permission'])
            ->where('skill_name = ?', $skill->getName());

        $rows = $connection->fetchAll($select);
        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['admin_user_id']] = $row['permission'];
        }
        return $result;
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('maggy/skills/savePermissions');
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('maggy/skills/index');
    }
}
