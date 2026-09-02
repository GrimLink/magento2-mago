<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Test\Integration\Service\Skills;

use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Helper\Bootstrap;
use MaggyAssistant\Base\Service\Skills\PermissionChecker;
use MaggyAssistant\Base\Test\Integration\Fakes\FakeAuthorization;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PermissionCheckerTest extends TestCase
{
    private const ADMIN_USER_ID = 555001;
    private const OTHER_ADMIN_USER_ID = 555002;
    private const SKILL_NAME = 'order_manager';

    private ResourceConnection $resourceConnection;

    protected function setUp(): void
    {
        $this->resourceConnection = Bootstrap::getObjectManager()->get(ResourceConnection::class);
    }

    protected function tearDown(): void
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->delete(
            $this->resourceConnection->getTableName('maggy_skill_permission'),
            ['admin_user_id in (?)' => [self::ADMIN_USER_ID, self::OTHER_ADMIN_USER_ID]]
        );
    }

    #[Test]
    public function itAllowsReadAndWriteWhenTheGrantIsWrite(): void
    {
        $this->grant(self::ADMIN_USER_ID, 'write');
        $checker = $this->newChecker();

        self::assertTrue($checker->isAllowed(self::ADMIN_USER_ID, self::SKILL_NAME, 'read'));
        self::assertTrue($checker->isAllowed(self::ADMIN_USER_ID, self::SKILL_NAME, 'write'));
    }

    #[Test]
    public function itAllowsOnlyReadWhenTheGrantIsRead(): void
    {
        $this->grant(self::ADMIN_USER_ID, 'read');
        $checker = $this->newChecker();

        self::assertTrue($checker->isAllowed(self::ADMIN_USER_ID, self::SKILL_NAME, 'read'));
        self::assertFalse($checker->isAllowed(self::ADMIN_USER_ID, self::SKILL_NAME, 'write'));
    }

    #[Test]
    public function itDeniesEverythingWhenTheGrantIsDisabled(): void
    {
        $this->grant(self::ADMIN_USER_ID, 'disabled');
        $checker = $this->newChecker(true, true);

        self::assertFalse($checker->isAllowed(self::ADMIN_USER_ID, self::SKILL_NAME, 'read'));
        self::assertFalse($checker->isAllowed(self::ADMIN_USER_ID, self::SKILL_NAME, 'write'));
    }

    #[Test]
    public function itDeniesEverythingForAnUnknownStoredPermissionValue(): void
    {
        $this->grant(self::ADMIN_USER_ID, 'bogus-value');
        $checker = $this->newChecker(true, true);

        self::assertFalse($checker->isAllowed(self::ADMIN_USER_ID, self::SKILL_NAME, 'read'));
        self::assertFalse($checker->isAllowed(self::ADMIN_USER_ID, self::SKILL_NAME, 'write'));
    }

    #[Test]
    public function itFallsBackToTheAclWhenThereIsNoStoredGrant(): void
    {
        $allowedByAcl = $this->newChecker(true, true);
        $deniedByAcl = $this->newChecker(false, false);

        self::assertTrue($allowedByAcl->isAllowed(self::ADMIN_USER_ID, self::SKILL_NAME, 'read'));
        self::assertTrue($allowedByAcl->isAllowed(self::ADMIN_USER_ID, self::SKILL_NAME, 'write'));
        self::assertFalse($deniedByAcl->isAllowed(self::ADMIN_USER_ID, self::SKILL_NAME, 'read'));
        self::assertFalse($deniedByAcl->isAllowed(self::ADMIN_USER_ID, self::SKILL_NAME, 'write'));
    }

    #[Test]
    public function itDefaultsToReadWhenNoActionIsGiven(): void
    {
        $this->grant(self::ADMIN_USER_ID, 'read');
        $checker = $this->newChecker();

        self::assertTrue($checker->isAllowed(self::ADMIN_USER_ID, self::SKILL_NAME));
    }

    #[Test]
    public function itFetchesPermissionsOncePerUserAndReusesThemAcrossCalls(): void
    {
        $this->grant(self::ADMIN_USER_ID, 'write');
        $checker = $this->newChecker();

        self::assertTrue($checker->isAllowed(self::ADMIN_USER_ID, self::SKILL_NAME, 'write'));

        // Mutate the row directly: if isAllowed() re-queried the DB, the next call would see 'disabled'.
        $this->grant(self::ADMIN_USER_ID, 'disabled');

        self::assertTrue($checker->isAllowed(self::ADMIN_USER_ID, self::SKILL_NAME, 'write'));
    }

    #[Test]
    public function itFetchesPermissionsSeparatelyPerUser(): void
    {
        $this->grant(self::ADMIN_USER_ID, 'disabled');
        $this->grant(self::OTHER_ADMIN_USER_ID, 'write');
        $checker = $this->newChecker();

        self::assertFalse($checker->isAllowed(self::ADMIN_USER_ID, self::SKILL_NAME, 'write'));
        self::assertTrue($checker->isAllowed(self::OTHER_ADMIN_USER_ID, self::SKILL_NAME, 'write'));
    }

    private function grant(int $adminUserId, string $permission): void
    {
        $this->resourceConnection->getConnection()->insertOnDuplicate(
            $this->resourceConnection->getTableName('maggy_skill_permission'),
            ['admin_user_id' => $adminUserId, 'skill_name' => self::SKILL_NAME, 'permission' => $permission],
            ['permission']
        );
    }

    private function newChecker(bool $aclReadAllowed = false, bool $aclWriteAllowed = false): PermissionChecker
    {
        return new PermissionChecker(
            $this->resourceConnection,
            new FakeAuthorization([
                'MaggyAssistant_Base::assistant_read' => $aclReadAllowed,
                'MaggyAssistant_Base::assistant_write' => $aclWriteAllowed,
            ])
        );
    }
}
