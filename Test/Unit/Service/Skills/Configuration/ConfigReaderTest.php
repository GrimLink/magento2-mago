<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Skills\Configuration;

use Magento\Framework\App\Config\ScopeConfigInterface;
use MagoAssistant\Mago\Service\Skills\Configuration\ConfigReader;
use MagoAssistant\Mago\Service\Store\StoreScopeContext;
use MagoAssistant\Mago\Test\Unit\Fakes\BuildsStoreLayouts;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConfigReaderTest extends TestCase
{
    use BuildsStoreLayouts;

    private const PATH = 'general/store_information/name';

    #[Test]
    public function itReadsTheDefaultScopeAndReportsStoreViewOverrides(): void
    {
        $reader = $this->readerWith([
            'default:0' => 'Main Shop',
            'websites:1' => 'Main Shop',
            'stores:1' => 'Main Shop',
            'stores:2' => 'Luma Shop',
        ]);

        $result = $reader->execute(['path' => self::PATH]);

        self::assertSame('Main Shop', $result['value']);
        self::assertSame('default', $result['scope']);
        self::assertSame(0, $result['scope_id']);
        self::assertStringStartsWith('default scope', $result['scope_label']);
        self::assertSame(
            [[
                'scope' => 'stores',
                'scope_id' => 2,
                'scope_label' => 'store view "Luma" (id 2, code "luma")',
                'value' => 'Luma Shop',
            ]],
            $result['overrides']
        );
        self::assertStringContainsString('Mention them to the user', $result['note']);
    }

    #[Test]
    public function itReportsAWebsiteOverrideOnceInsteadOfPerStoreView(): void
    {
        $reader = $this->readerWith([
            'default:0' => 'Main Shop',
            'websites:1' => 'Website Shop',
            'stores:1' => 'Website Shop',
            'stores:2' => 'Website Shop',
        ]);

        $result = $reader->execute(['path' => self::PATH]);

        self::assertCount(1, $result['overrides']);
        self::assertSame('websites', $result['overrides'][0]['scope']);
        self::assertSame(1, $result['overrides'][0]['scope_id']);
    }

    #[Test]
    public function itSaysSoWhenNothingOverridesTheDefault(): void
    {
        $reader = $this->readerWith([
            'default:0' => 'Main Shop',
            'websites:1' => 'Main Shop',
            'stores:1' => 'Main Shop',
            'stores:2' => 'Main Shop',
        ]);

        $result = $reader->execute(['path' => self::PATH]);

        self::assertSame([], $result['overrides']);
        self::assertStringContainsString('the default applies everywhere', $result['note']);
    }

    #[Test]
    public function itSkipsOverrideLookupOnASingleStoreView(): void
    {
        $reader = $this->readerWith(['default:0' => 'Main Shop'], $this->singleStoreManager());

        $result = $reader->execute(['path' => self::PATH]);

        self::assertArrayNotHasKey('overrides', $result);
        self::assertArrayNotHasKey('note', $result);
    }

    #[Test]
    public function itReadsAStoreViewScopeWithoutOverrides(): void
    {
        $reader = $this->readerWith(['stores:2' => 'Luma Shop']);

        $result = $reader->execute(['path' => self::PATH, 'scope' => 'stores', 'scope_id' => 2]);

        self::assertSame('Luma Shop', $result['value']);
        self::assertSame('store view "Luma" (id 2, code "luma")', $result['scope_label']);
        self::assertArrayNotHasKey('overrides', $result);
    }

    #[Test]
    public function itRejectsAnUnknownStoreViewBeforeReading(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects(self::never())->method('getValue');
        $reader = new ConfigReader($scopeConfig, new StoreScopeContext($this->multiStoreManager()));

        $result = $reader->execute(['path' => self::PATH, 'scope' => 'stores', 'scope_id' => 9]);

        self::assertStringStartsWith('Unknown store view id 9', $result['error']);
    }

    #[Test]
    public function itRejectsAnUnknownScope(): void
    {
        $reader = $this->readerWith([]);

        $result = $reader->execute(['path' => self::PATH, 'scope' => 'global']);

        self::assertStringStartsWith('Unknown scope "global"', $result['error']);
    }

    #[Test]
    public function itStillBlocksSensitivePaths(): void
    {
        $reader = $this->readerWith([]);

        $result = $reader->execute(['path' => 'payment/checkmo/title']);

        self::assertArrayHasKey('error', $result);
    }

    /**
     * @param array<string, mixed> $values "scope:id" => value
     */
    private function readerWith(array $values, $storeManager = null): ConfigReader
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path, string $scope, $scopeId) => $values[$scope . ':' . (int)$scopeId] ?? null
        );

        return new ConfigReader(
            $scopeConfig,
            new StoreScopeContext($storeManager ?? $this->multiStoreManager())
        );
    }
}
