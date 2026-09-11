<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Skills;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\AuthorizationInterface;

class PermissionChecker
{
    /** @var array<int, array<string, string>> */
    private array $permissionsByUser = [];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly AuthorizationInterface $authorization
    ) {
    }

    public function isAllowed(int $adminUserId, string $skillName, string $action = 'read'): bool
    {
        $permission = $this->getPermission($adminUserId, $skillName);

        if ($permission !== null) {
            return match ($permission) {
                'write' => true,
                'read' => $action === 'read',
                default => false,
            };
        }

        // Fallback to standard ACL
        if ($action === 'write') {
            return $this->authorization->isAllowed('MagoAssistant_Mago::assistant_write');
        }
        return $this->authorization->isAllowed('MagoAssistant_Mago::assistant_read');
    }

    private function getPermission(int $adminUserId, string $skillName): ?string
    {
        if (!isset($this->permissionsByUser[$adminUserId])) {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('mago_skill_permission');

            $this->permissionsByUser[$adminUserId] = $connection->fetchPairs(
                $connection->select()
                    ->from($table, ['skill_name', 'permission'])
                    ->where('admin_user_id = ?', $adminUserId)
            );
        }

        return $this->permissionsByUser[$adminUserId][$skillName] ?? null;
    }
}
