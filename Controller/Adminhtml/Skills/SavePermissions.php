<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Controller\Adminhtml\Skills;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\App\ResourceConnection;

class SavePermissions extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MaggyAssistant_Base::config';

    public function __construct(
        Context $context,
        private readonly ResourceConnection $resourceConnection
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $skillName = $this->getRequest()->getParam('skill_name', '');
        $permissions = $this->getRequest()->getParam('permissions', []);

        if (!$skillName) {
            $this->messageManager->addErrorMessage(__('Skill name is required.'));
            return $this->resultRedirectFactory->create()->setPath('maggy/skills/index');
        }

        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('maggy_skill_permission');

            foreach ($permissions as $userId => $permission) {
                $userId = (int)$userId;
                if (!$userId) {
                    continue;
                }

                if ($permission === '' || $permission === null) {
                    $connection->delete($table, [
                        'admin_user_id = ?' => $userId,
                        'skill_name = ?' => $skillName,
                    ]);
                    continue;
                }

                if (!in_array($permission, ['read', 'write', 'disabled'])) {
                    continue;
                }

                $connection->insertOnDuplicate($table, [
                    'admin_user_id' => $userId,
                    'skill_name' => $skillName,
                    'permission' => $permission,
                ], ['permission']);
            }

            $this->messageManager->addSuccessMessage(__('Permissions saved for skill "%1".', $skillName));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Error saving permissions: %1', $e->getMessage()));
        }

        return $this->resultRedirectFactory->create()->setPath(
            'maggy/skills/edit',
            ['skill_name' => $skillName]
        );
    }
}
