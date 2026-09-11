<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Model\Config\System;

use Magento\Config\Model\ResourceModel\Config as ConfigData;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory as ConfigDataCollectionFactory;
use Magento\Framework\App\Config\ScopeCodeResolver;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use MagoAssistant\Mago\Api\Config\RepositoryInterface as ConfigRepositoryInterface;

class BaseRepository
{
    private array $loadedStores = [];
    private array $baseUrls = [];
    private ?array $composerData = null;

    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected ScopeConfigInterface $scopeConfig,
        protected ConfigDataCollectionFactory $configDataCollectionFactory,
        protected ConfigData $config,
        protected Json $json,
        protected ProductMetadataInterface $metadata,
        protected EncryptorInterface $encryptor,
        protected ResourceConnection $resourceConnection,
        protected ScopeCodeResolver $scopeCodeResolver,
        protected DateTime $dateTime,
        protected ComponentRegistrarInterface $componentRegistrar,
        protected FileDriver $fileDriver
    ) {
    }

    public function setConfigData(string|int|float|bool|null $value, string $key, ?int $storeId = null): void
    {
        $scope = $storeId ? 'stores' : 'default';
        $scopeId = $storeId ?: 0;
        $this->config->saveConfig($key, (string)$value, $scope, $scopeId);
    }

    protected function getStoreValueArray(string $path, ?int $storeId = null, ?string $scope = null): array
    {
        $value = $this->getStoreValue($path, $storeId, $scope);
        if (!$value) {
            return [];
        }

        try {
            $decoded = $this->json->unserialize($value);
        } catch (\Throwable $e) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    protected function getStoreValue(string $path, ?int $storeId = null, ?string $scope = null): string
    {
        $storeId = $storeId ?: (int)$this->getStore()->getId();
        $scope = $scope ?? ScopeInterface::SCOPE_STORE;
        return (string)$this->scopeConfig->getValue($path, $scope, $storeId);
    }

    public function getStore(?int $storeId = null): StoreInterface
    {
        if ($storeId && isset($this->loadedStores[$storeId])) {
            return $this->loadedStores[$storeId];
        }

        try {
            $store = $storeId ? $this->storeManager->getStore($storeId) : $this->storeManager->getStore();
            if ($storeId) {
                $this->loadedStores[$storeId] = $store;
            }
            return $store;
        } catch (\Throwable $e) {
            $defaultStore = $this->storeManager->getDefaultStoreView();
            if ($defaultStore !== null) {
                return $defaultStore;
            }

            $stores = $this->storeManager->getStores();
            $firstStore = reset($stores);
            if ($firstStore === false) {
                throw new NoSuchEntityException(__('No store view is available.'));
            }

            return $firstStore;
        }
    }

    protected function getUncachedStoreValue(string $path, ?int $storeId = null): string
    {
        $collection = $this->configDataCollectionFactory->create()
            ->addFieldToSelect('value')
            ->addFieldToFilter('path', $path);

        if ($storeId) {
            $collection->addFieldToFilter('scope_id', (string)$storeId)->addFieldToFilter('scope', 'stores');
        } else {
            $collection->addFieldToFilter('scope_id', '0')->addFieldToFilter('scope', 'default');
        }

        return (string)$collection->getFirstItem()->getData('value');
    }

    protected function isSetFlag(string $path, ?int $storeId = null, ?string $scope = null): bool
    {
        $scope = $scope ?? ScopeInterface::SCOPE_STORE;
        $storeId = $storeId ?: (int)$this->getStore()->getId();
        return $this->scopeConfig->isSetFlag($path, $scope, $storeId);
    }

    protected function getBaseUrl(int $storeId): string
    {
        if (empty($this->baseUrls[$storeId])) {
            $this->baseUrls[$storeId] = $this->getStore($storeId)->getBaseUrl();
        }

        return $this->baseUrls[$storeId];
    }

    protected function getComposerData(): array
    {
        if ($this->composerData !== null) {
            return $this->composerData;
        }

        $this->composerData = [];

        $path = $this->componentRegistrar->getPath('module', ConfigRepositoryInterface::EXTENSION_CODE);
        if (!$path) {
            return $this->composerData;
        }

        try {
            $filePath = $path . '/composer.json';
            if ($this->fileDriver->isExists($filePath)) {
                $decoded = $this->json->unserialize(
                    $this->fileDriver->fileGetContents($filePath)
                );
                $this->composerData = is_array($decoded) ? $decoded : [];
            }
        } catch (\Throwable $e) {
            $this->composerData = [];
        }

        return $this->composerData;
    }
}
