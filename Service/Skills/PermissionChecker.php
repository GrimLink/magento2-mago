<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Skills;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\AuthorizationInterface;

class PermissionChecker
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly AuthorizationInterface $authorization
    ) {
    }

    public function isAllowed(int $adminUserId, string $skillName, string $action = 'read'): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('maggy_skill_permission');

        $permission = $connection->fetchOne(
            $connection->select()
                ->from($table, ['permission'])
                ->where('admin_user_id = ?', $adminUserId)
                ->where('skill_name = ?', $skillName)
        );

        if ($permission !== false) {
            if ($permission === 'disabled') return false;
            if ($permission === 'read') return $action === 'read';
            if ($permission === 'write') return true;
        }

        // Fallback to standard ACL
        if ($action === 'write') {
            return $this->authorization->isAllowed('MaggyAssistant_Base::assistant_write');
        }
        return $this->authorization->isAllowed('MaggyAssistant_Base::assistant_read');
    }
}
