<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Skills\System\ModuleVersion;

use MagoAssistant\Mago\Api\ModuleInfo\RepositoryInterface;
use MagoAssistant\Mago\Service\Skills\System\ModuleVersion\GetVersionAction;
use MagoAssistant\Mago\Test\Unit\Fakes\FakeModuleInfoRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GetVersionActionTest extends TestCase
{
    #[Test]
    public function itReturnsTheComposerVersionForAnExactModuleName(): void
    {
        $moduleInfoRepository = (new FakeModuleInfoRepository())->withModule(
            'Magmodules_Channable',
            'magmodules/module-channable',
            '4.2.0',
            RepositoryInterface::VERSION_SOURCE_COMPOSER
        );

        $result = $this->executeWith($moduleInfoRepository, 'Magmodules_Channable');

        self::assertSame('Magmodules_Channable', $result['module_name']);
        self::assertSame('magmodules/module-channable', $result['package_name']);
        self::assertSame('4.2.0', $result['version']);
        self::assertSame(RepositoryInterface::VERSION_SOURCE_COMPOSER, $result['version_source']);
        self::assertTrue($result['is_enabled']);
    }

    #[Test]
    public function itFuzzyMatchesAHumanReadableName(): void
    {
        $moduleInfoRepository = (new FakeModuleInfoRepository())->withModule(
            'Magmodules_Channable',
            'magmodules/module-channable',
            '4.2.0',
            RepositoryInterface::VERSION_SOURCE_COMPOSER
        );

        $result = $this->executeWith($moduleInfoRepository, 'Magmodules Channable module');

        self::assertSame('Magmodules_Channable', $result['module_name']);
    }

    #[Test]
    public function itFuzzyMatchesOnAPartialVendorlessName(): void
    {
        $moduleInfoRepository = (new FakeModuleInfoRepository())->withModule(
            'Magmodules_Channable',
            'magmodules/module-channable',
            '4.2.0',
            RepositoryInterface::VERSION_SOURCE_COMPOSER
        );

        $result = $this->executeWith($moduleInfoRepository, 'channable');

        self::assertSame('Magmodules_Channable', $result['module_name']);
    }

    #[Test]
    public function itFallsBackToTheModuleXmlSetupVersionWhenComposerHasNone(): void
    {
        $moduleInfoRepository = (new FakeModuleInfoRepository())->withModule(
            'Acme_CustomShipping',
            '',
            '2.0.1',
            RepositoryInterface::VERSION_SOURCE_MODULE_XML
        );

        $result = $this->executeWith($moduleInfoRepository, 'CustomShipping');

        self::assertSame('2.0.1', $result['version']);
        self::assertSame(RepositoryInterface::VERSION_SOURCE_MODULE_XML, $result['version_source']);
    }

    #[Test]
    public function itPassesThroughTheComposerJsonSourceWhenComposerDoesNotKnowThePackage(): void
    {
        $moduleInfoRepository = (new FakeModuleInfoRepository())->withModule(
            'Acme_CustomShipping',
            'acme/module-custom-shipping',
            '2.4.0',
            RepositoryInterface::VERSION_SOURCE_COMPOSER_JSON
        );

        $result = $this->executeWith($moduleInfoRepository, 'CustomShipping');

        self::assertSame('2.4.0', $result['version']);
        self::assertSame(RepositoryInterface::VERSION_SOURCE_COMPOSER_JSON, $result['version_source']);
    }

    #[Test]
    public function itReportsUnknownVersionWhenNeitherSourceHasOne(): void
    {
        $moduleInfoRepository = (new FakeModuleInfoRepository())->withModule('Acme_CustomShipping');

        $result = $this->executeWith($moduleInfoRepository, 'CustomShipping');

        self::assertSame('unknown', $result['version']);
        self::assertSame(RepositoryInterface::VERSION_SOURCE_UNKNOWN, $result['version_source']);
    }

    #[Test]
    public function itReportsDisabledModulesInsteadOfHidingThem(): void
    {
        $moduleInfoRepository = (new FakeModuleInfoRepository())->withModule(
            'Acme_CustomShipping',
            'acme/module-custom-shipping',
            '2.0.1',
            RepositoryInterface::VERSION_SOURCE_COMPOSER,
            false
        );

        $result = $this->executeWith($moduleInfoRepository, 'CustomShipping');

        self::assertFalse($result['is_enabled']);
    }

    #[Test]
    public function itResolvesAnExactNameEvenWhenOtherModulesShareAWord(): void
    {
        $composerSource = RepositoryInterface::VERSION_SOURCE_COMPOSER;
        $moduleInfoRepository = (new FakeModuleInfoRepository())
            ->withModule('Magento_Catalog', 'magento/module-catalog', '104.0.9', $composerSource)
            ->withModule('Magento_CatalogRule', 'magento/module-catalog-rule', '101.2.9', $composerSource)
            ->withModule('Magento_CatalogSearch', 'magento/module-catalog-search', '102.0.9', $composerSource);

        $result = $this->executeWith($moduleInfoRepository, 'Magento Catalog');

        self::assertSame('Magento_Catalog', $result['module_name']);
        self::assertArrayNotHasKey('ambiguous', $result);
    }

    #[Test]
    public function itAsksForClarificationWhenMultipleModulesMatch(): void
    {
        $moduleInfoRepository = (new FakeModuleInfoRepository())
            ->withModule('Magmodules_Channable', 'magmodules/module-channable', '4.2.0')
            ->withModule('Magmodules_ChannableStock', 'magmodules/module-channable-stock', '1.1.0');

        $result = $this->executeWith($moduleInfoRepository, 'Magmodules');

        self::assertTrue($result['ambiguous']);
        self::assertCount(2, $result['candidates']);
    }

    #[Test]
    public function itReturnsAnErrorWhenNoModuleMatches(): void
    {
        $moduleInfoRepository = new FakeModuleInfoRepository();

        $result = $this->executeWith($moduleInfoRepository, 'DoesNotExist');

        self::assertArrayHasKey('error', $result);
    }

    #[Test]
    public function itReturnsAnErrorWhenModuleNameIsMissing(): void
    {
        $moduleInfoRepository = new FakeModuleInfoRepository();

        $result = (new GetVersionAction($moduleInfoRepository))->execute([], 1);

        self::assertArrayHasKey('error', $result);
    }

    #[Test]
    public function itIsReadOnly(): void
    {
        $action = new GetVersionAction(new FakeModuleInfoRepository());

        self::assertTrue($action->isReadOnly());
    }

    private function executeWith(FakeModuleInfoRepository $moduleInfoRepository, string $moduleName): array
    {
        return (new GetVersionAction($moduleInfoRepository))->execute(['module_name' => $moduleName], 1);
    }
}
