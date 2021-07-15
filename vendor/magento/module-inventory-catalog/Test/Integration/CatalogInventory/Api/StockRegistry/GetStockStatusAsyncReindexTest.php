<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Integration\CatalogInventory\Api\StockRegistry;

use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\MessageQueue\ConsumerFactory;
use Magento\Framework\MessageQueue\QueueFactoryInterface;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Verify product stock status with async reindex.
 */
class GetStockStatusAsyncReindexTest extends TestCase
{
    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * @var GetProductIdsBySkusInterface
     */
    private $getProductIdsBySkus;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var string
     */
    private $storeCodeBefore;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->stockRegistry = Bootstrap::getObjectManager()->get(StockRegistryInterface::class);
        $this->getProductIdsBySkus = Bootstrap::getObjectManager()->get(GetProductIdsBySkusInterface::class);
        $this->storeManager = Bootstrap::getObjectManager()->get(StoreManagerInterface::class);
        $this->storeCodeBefore = $this->storeManager->getStore()->getCode();
    }

    /**
     * @magentoConfigFixture default/cataloginventory/indexer/strategy async
     *
     * @magentoDataFixture Magento_InventoryApi::Test/_files/products.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/sources.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stocks.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stock_source_links.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/source_items.php
     * @magentoDataFixture Magento_InventorySalesApi::Test/_files/websites_with_stores.php
     * @magentoDataFixture Magento_InventorySalesApi::Test/_files/stock_website_sales_channels.php
     * @magentoDataFixture Magento_InventoryIndexer::Test/_files/reindex_inventory.php
     * @magentoDbIsolation disabled
     *
     * @param string $storeCode
     * @param string $sku
     * @param int $status
     * @param float $qty
     * @return void
     *
     * @dataProvider getStatusDataProvider
     */
    public function testGetStatusIfScopeIdParameterIsNotPassed(
        string $storeCode,
        string $sku,
        int $status,
        float $qty
    ): void {
        $this->storeManager->setCurrentStore($storeCode);
        $productId = $this->getProductIdsBySkus->execute([$sku])[$sku];
        $this->runConsumers();
        $stockStatus = $this->stockRegistry->getStockStatus($productId);

        self::assertEquals($status, $stockStatus->getStockStatus());
        self::assertEquals($qty, $stockStatus->getQty());
    }

    /**
     * @magentoConfigFixture default/cataloginventory/indexer/strategy async
     *
     * @magentoDataFixture Magento_InventoryApi::Test/_files/products.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/sources.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stocks.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stock_source_links.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/source_items.php
     * @magentoDataFixture Magento_InventorySalesApi::Test/_files/websites_with_stores.php
     * @magentoDataFixture Magento_InventorySalesApi::Test/_files/stock_website_sales_channels.php
     * @magentoDataFixture Magento_InventoryIndexer::Test/_files/reindex_inventory.php
     * @magentoDbIsolation disabled
     *
     * @param string $storeCode
     * @param string $sku
     * @param int $status
     * @param float $qty
     * @return void
     *
     * @dataProvider getStatusDataProvider
     */
    public function testGetStatusIfScopeIdParameterIsPassed(
        string $storeCode,
        string $sku,
        int $status,
        float $qty
    ): void {
        $this->storeManager->setCurrentStore($storeCode);
        $websiteId = $this->storeManager->getWebsite()->getId();
        $productId = $this->getProductIdsBySkus->execute([$sku])[$sku];
        $this->runConsumers();
        $stockStatus = $this->stockRegistry->getStockStatus($productId, $websiteId);

        self::assertEquals($status, $stockStatus->getStockStatus());
        self::assertEquals($qty, $stockStatus->getQty());
    }

    /**
     * @return array
     */
    public function getStatusDataProvider(): array
    {
        return [
            ['store_for_eu_website', 'SKU-1', 1, 8.5],
            ['store_for_eu_website', 'SKU-2', 0, 0],
            ['store_for_us_website', 'SKU-2', 1, 5],
            ['store_for_us_website', 'SKU-3', 0, 0],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        $this->storeManager->setCurrentStore($this->storeCodeBefore);

        parent::tearDown();
    }

    /**
     * Run consumers to reindex stock.
     *
     * @return void
     */
    private function runConsumers(): void
    {
        $consumerFactory = Bootstrap::getObjectManager()->get(ConsumerFactory::class);
        $consumer = $consumerFactory->get('inventory.indexer.stock');
        $consumer->process(2);
        /*Wait till stock will be reindex asynchronously.*/
        sleep(20);
    }
}
