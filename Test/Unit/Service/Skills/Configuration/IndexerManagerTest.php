<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Service\Skills\Configuration;

use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Indexer\Model\Indexer\Collection;
use Magento\Indexer\Model\Indexer\CollectionFactory;
use MagoAssistant\Mago\Service\Skills\Configuration\IndexerManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IndexerManagerTest extends TestCase
{
    private const INDEXERS = [
        'catalog_product_price' => 'Product Price',
        'cataloginventory_stock' => 'Stock',
    ];

    #[Test]
    public function reindexSchemaOffersTheLiveIndexersAsAnEnum(): void
    {
        $schema = $this->manager()->getParameterSchemaForActions(['reindex']);

        $indexerId = $schema['properties']['indexer_id'];
        self::assertSame(['catalog_product_price', 'cataloginventory_stock'], $indexerId['enum']);
        self::assertStringContainsString('catalog_product_price (Product Price)', $indexerId['description']);
        self::assertStringContainsString('cataloginventory_stock (Stock)', $indexerId['description']);
    }

    #[Test]
    public function findRefusalPassesAKnownIndexerAndRefusesAnInventedOne(): void
    {
        $manager = $this->manager();

        self::assertNull($manager->findRefusal(['action' => 'reindex', 'indexer_id' => 'cataloginventory_stock']));
        self::assertNull($manager->findRefusal(['action' => 'set_mode', 'indexer_id' => 'catalog_product_price']));

        $refusal = $manager->findRefusal(['action' => 'reindex', 'indexer_id' => 'cataloginventory_stock_stock']);
        self::assertIsArray($refusal);
        self::assertStringContainsString('Unknown indexer "cataloginventory_stock_stock"', $refusal['error']);
        self::assertSame(
            [
                ['id' => 'catalog_product_price', 'title' => 'Product Price'],
                ['id' => 'cataloginventory_stock', 'title' => 'Stock'],
            ],
            $refusal['valid_indexers']
        );
    }

    #[Test]
    public function findRefusalIgnoresReadsReindexAllAndAnEmptyId(): void
    {
        $manager = $this->manager();

        self::assertNull($manager->findRefusal(['action' => 'status']));
        self::assertNull($manager->findRefusal(['action' => 'reindex_all']));
        self::assertNull($manager->findRefusal(['action' => 'reindex', 'indexer_id' => '']));
    }

    #[Test]
    public function aRunawayIndexerIdIsTruncatedInTheRefusalAndTheExecuteError(): void
    {
        $manager = $this->manager();
        $huge = str_repeat('x', 5000);

        $refusal = $manager->findRefusal(['action' => 'reindex', 'indexer_id' => $huge]);
        self::assertIsArray($refusal);
        self::assertStringContainsString(str_repeat('x', 100) . '…', $refusal['error']);
        self::assertLessThan(200, mb_strlen($refusal['error']));

        $result = $manager->execute(['action' => 'reindex', 'indexer_id' => $huge]);
        self::assertStringContainsString(str_repeat('x', 100) . '…', $result['error']);
        self::assertStringNotContainsString(str_repeat('x', 101), $result['error']);
    }

    private function manager(): IndexerManager
    {
        $items = [];
        foreach (self::INDEXERS as $id => $title) {
            $indexer = $this->createStub(IndexerInterface::class);
            $indexer->method('getId')->willReturn($id);
            $indexer->method('getTitle')->willReturn($title);
            $items[] = $indexer;
        }

        $collection = $this->createStub(Collection::class);
        $collection->method('getItems')->willReturn($items);
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        // Unknown ids throw, mirroring IndexerRegistry for a non-existent indexer.
        $registry = $this->createStub(IndexerRegistry::class);
        $registry->method('get')->willThrowException(new \InvalidArgumentException('not found'));

        return new IndexerManager($factory, $registry);
    }
}
