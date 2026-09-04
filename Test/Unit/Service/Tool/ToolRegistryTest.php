<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Test\Unit\Service\Tool;

use Magento\Framework\AuthorizationInterface;
use MaggyAssistant\Base\Service\Skills\PermissionChecker;
use MaggyAssistant\Base\Service\Tool\ToolRegistry;
use MaggyAssistant\Base\Test\Unit\Fakes\FakeAction;
use MaggyAssistant\Base\Test\Unit\Fakes\FakeSkill;
use MaggyAssistant\Base\Test\Unit\Fakes\FakeTool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ToolRegistryTest extends TestCase
{
    private const ADMIN_ID = 7;

    private FakeSkill $cmsData;
    private FakeTool $cacheManager;

    protected function setUp(): void
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $this->cmsData = new FakeSkill('cms_data', $authorization, [
            'list_pages' => new FakeAction('list_pages', true, ['limit' => ['type' => 'integer']]),
            'update_page' => new FakeAction('update_page', false, [
                'limit' => ['type' => 'integer'],
                'content' => ['type' => 'string'],
            ]),
        ]);
        $this->cacheManager = new FakeTool('cache_manager', ['status', 'flush'], ['status']);
    }

    #[Test]
    public function readGrantNarrowsDescriptionEnumAndParamsOfActionScopedTool(): void
    {
        $registry = $this->registry(['cms_data' => 'read']);

        $definition = $registry->getToolDefinition($this->cmsData, self::ADMIN_ID);

        self::assertSame('Fake cms_data. Actions: "list_pages" (list_pages description).', $definition['description']);
        self::assertSame(['list_pages'], $definition['parameters']['properties']['action']['enum']);
        self::assertArrayHasKey('limit', $definition['parameters']['properties']);
        self::assertArrayNotHasKey('content', $definition['parameters']['properties']);
    }

    #[Test]
    public function writeGrantAdvertisesTheFullTool(): void
    {
        $registry = $this->registry(['cms_data' => 'write']);

        $definition = $registry->getToolDefinition($this->cmsData, self::ADMIN_ID);

        self::assertStringContainsString('"update_page"', $definition['description']);
        self::assertSame(['list_pages', 'update_page'], $definition['parameters']['properties']['action']['enum']);
        self::assertArrayHasKey('content', $definition['parameters']['properties']);
    }

    #[Test]
    public function readGrantOnlyFiltersTheEnumOfAToolThatIsNotActionScoped(): void
    {
        $registry = $this->registry(['cache_manager' => 'read']);

        $definition = $registry->getToolDefinition($this->cacheManager, self::ADMIN_ID);

        self::assertSame(['status'], $definition['parameters']['properties']['action']['enum']);
        self::assertSame($this->cacheManager->getDescription(), $definition['description']);
    }

    #[Test]
    public function toolDefinitionsOnlyContainToolsTheUserMayInvoke(): void
    {
        $registry = $this->registry(['cms_data' => 'read', 'cache_manager' => 'disabled']);

        $definitions = $registry->getToolDefinitions(self::ADMIN_ID);

        self::assertSame(['cms_data'], array_column($definitions, 'name'));
    }

    #[Test]
    public function writeAccessFollowsTheGrant(): void
    {
        $registry = $this->registry(['cms_data' => 'read', 'cache_manager' => 'write']);

        self::assertFalse($registry->hasWriteAccess($this->cmsData, self::ADMIN_ID));
        self::assertTrue($registry->hasWriteAccess($this->cacheManager, self::ADMIN_ID));
    }

    #[Test]
    public function parameterSchemaIsBuiltOncePerToolPerRequest(): void
    {
        $registry = $this->registry(['cms_data' => 'read', 'cache_manager' => 'read']);

        $registry->getToolDefinitions(self::ADMIN_ID);
        $registry->getEnabledTools(self::ADMIN_ID);
        $registry->getTool('cache_manager', self::ADMIN_ID);
        $registry->getToolDefinition($this->cacheManager, self::ADMIN_ID);

        self::assertSame(1, $this->cacheManager->getSchemaCalls());
    }

    #[Test]
    public function withoutPermissionCheckerEverythingIsAdvertisedUnfiltered(): void
    {
        $registry = new ToolRegistry(null, [$this->cmsData, $this->cacheManager]);

        $definitions = $registry->getToolDefinitions(self::ADMIN_ID);

        self::assertCount(2, $definitions);
        self::assertSame(['list_pages', 'update_page'], $definitions[0]['parameters']['properties']['action']['enum']);
        self::assertTrue($registry->hasWriteAccess($this->cmsData, self::ADMIN_ID));
    }

    /**
     * @param array<string, string> $grants skill name => read|write|disabled
     */
    private function registry(array $grants): ToolRegistry
    {
        $checker = $this->createMock(PermissionChecker::class);
        $checker->method('isAllowed')->willReturnCallback(
            static function (int $adminUserId, string $skill, string $action) use ($grants): bool {
                if ($adminUserId !== self::ADMIN_ID) {
                    return false;
                }
                return match ($grants[$skill] ?? 'disabled') {
                    'write' => true,
                    'read' => $action === 'read',
                    default => false,
                };
            }
        );

        return new ToolRegistry($checker, [$this->cmsData, $this->cacheManager]);
    }
}
