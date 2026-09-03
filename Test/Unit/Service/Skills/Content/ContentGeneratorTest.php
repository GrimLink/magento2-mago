<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Test\Unit\Service\Skills\Content;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use MaggyAssistant\Base\Service\Skills\Content\ContentGenerator;
use MaggyAssistant\Base\Service\Store\StoreScopeContext;
use MaggyAssistant\Base\Test\Unit\Fakes\BuildsStoreLayouts;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ContentGeneratorTest extends TestCase
{
    use BuildsStoreLayouts;

    private const SKU = 'MH01';
    private const TEXT = 'New default description';

    #[Test]
    public function itLoadsAndSavesAtTheDefaultScopeWhenNoStoreIsGiven(): void
    {
        $product = $this->product(self::TEXT);
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->method('get')->willReturnCallback(
            fn (string $sku, bool $editMode, $storeId) => $storeId === 0 ? $product : $this->product(self::TEXT)
        );
        $repository->expects(self::once())->method('save')->with($product);
        $product->expects(self::once())->method('setData')->with('description', self::TEXT);

        $result = $this->generatorWith($repository)->execute([
            'action' => 'generate_description',
            'sku' => self::SKU,
            'content' => self::TEXT,
        ]);

        self::assertTrue($result['success']);
        self::assertSame(0, $result['store_id']);
        self::assertSame('default scope (shared by all store views)', $result['scope_label']);
        self::assertArrayNotHasKey('overridden_in', $result);
    }

    #[Test]
    public function itReportsStoreViewsThatStillOverrideTheField(): void
    {
        $global = $this->product(self::TEXT);
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->method('get')->willReturnCallback(
            fn (string $sku, bool $editMode, $storeId) => match ((int)$storeId) {
                0 => $global,
                1 => $this->product('Old text pinned to Hyva'),
                default => $this->product(self::TEXT),
            }
        );

        $result = $this->generatorWith($repository)->execute([
            'action' => 'generate_description',
            'sku' => self::SKU,
            'content' => self::TEXT,
        ]);

        self::assertSame(
            [['store_id' => 1, 'store_label' => 'store view "Hyva" (id 1, code "default")']],
            $result['overridden_in']
        );
        self::assertStringContainsString('keep their own value', $result['note']);
    }

    #[Test]
    public function itLoadsAndSavesInTheRequestedStoreView(): void
    {
        $product = $this->product('');
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects(self::once())->method('get')->with(self::SKU, false, 2)->willReturn($product);
        $repository->expects(self::once())->method('save')->with($product);

        $result = $this->generatorWith($repository)->execute([
            'action' => 'generate_short_description',
            'sku' => self::SKU,
            'content' => 'Korte tekst',
            'store_id' => 2,
        ]);

        self::assertSame(2, $result['store_id']);
        self::assertSame('store view "Luma" (id 2, code "luma")', $result['scope_label']);
        self::assertArrayNotHasKey('overridden_in', $result);
    }

    #[Test]
    public function itRejectsAnUnknownStoreViewBeforeTouchingTheProduct(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects(self::never())->method('get');

        $result = $this->generatorWith($repository)->execute([
            'action' => 'generate_description',
            'sku' => self::SKU,
            'store_id' => 42,
        ]);

        self::assertStringStartsWith('Unknown store view id 42', $result['error']);
    }

    #[Test]
    public function itPutsTheScopeIntoTheProductContext(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->method('get')->willReturn($this->product('Existing'));

        $result = $this->generatorWith($repository)->execute([
            'action' => 'generate_description',
            'sku' => self::SKU,
            'store_id' => 2,
        ]);

        self::assertSame(2, $result['product_context']['store_id']);
        self::assertSame('store view "Luma" (id 2, code "luma")', $result['product_context']['scope_label']);
    }

    private function product(string $description): Product&MockObject
    {
        $product = $this->createMock(Product::class);
        $product->method('getSku')->willReturn(self::SKU);
        $product->method('getData')->willReturnCallback(
            static fn (string $key = '') => $key === 'description' ? $description : null
        );
        $categories = $this->createMock(CategoryCollection::class);
        $categories->method('addAttributeToSelect')->willReturnSelf();
        $categories->method('getIterator')->willReturn(new \ArrayIterator([]));
        $product->method('getCategoryCollection')->willReturn($categories);

        return $product;
    }

    private function generatorWith(ProductRepositoryInterface $repository): ContentGenerator
    {
        return new ContentGenerator($repository, new StoreScopeContext($this->multiStoreManager()));
    }
}
