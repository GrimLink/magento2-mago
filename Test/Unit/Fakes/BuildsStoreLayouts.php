<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Fakes;

use Magento\Store\Api\Data\GroupInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Store manager doubles for the two layouts the scope logic has to tell apart.
 */
trait BuildsStoreLayouts
{
    /**
     * One website "Main Website" (base) with store group "Main Website Store" holding the store
     * views "Hyva" (id 1, default) and "Luma" (id 2).
     */
    protected function multiStoreManager(): StoreManagerInterface&MockObject
    {
        $hyva = $this->storeView(1, 'default', 'Hyva', 1, 1);
        $luma = $this->storeView(2, 'luma', 'Luma', 1, 1);

        return $this->storeManagerWith(
            [$this->website(1, 'base', 'Main Website', 1)],
            [$this->group(1, 'Main Website Store', 1, 1)],
            [$hyva, $luma],
            $hyva
        );
    }

    /**
     * One website with a single store view "Hyva" (id 1).
     */
    protected function singleStoreManager(): StoreManagerInterface&MockObject
    {
        $hyva = $this->storeView(1, 'default', 'Hyva', 1, 1);

        return $this->storeManagerWith(
            [$this->website(1, 'base', 'Main Website', 1)],
            [$this->group(1, 'Main Website Store', 1, 1)],
            [$hyva],
            $hyva
        );
    }

    /**
     * @param WebsiteInterface[] $websites
     * @param GroupInterface[] $groups
     * @param StoreInterface[] $stores
     */
    protected function storeManagerWith(
        array $websites,
        array $groups,
        array $stores,
        StoreInterface $defaultStore
    ): StoreManagerInterface&MockObject {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getWebsites')->willReturn($websites);
        $storeManager->method('getGroups')->willReturn($groups);
        $storeManager->method('getStores')->willReturn($stores);
        $storeManager->method('getDefaultStoreView')->willReturn($defaultStore);

        return $storeManager;
    }

    protected function website(int $id, string $code, string $name, int $defaultGroupId): WebsiteInterface
    {
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getId')->willReturn($id);
        $website->method('getCode')->willReturn($code);
        $website->method('getName')->willReturn($name);
        $website->method('getDefaultGroupId')->willReturn($defaultGroupId);

        return $website;
    }

    protected function group(int $id, string $name, int $websiteId, int $defaultStoreId): GroupInterface
    {
        $group = $this->createMock(GroupInterface::class);
        $group->method('getId')->willReturn($id);
        $group->method('getName')->willReturn($name);
        $group->method('getWebsiteId')->willReturn($websiteId);
        $group->method('getDefaultStoreId')->willReturn($defaultStoreId);

        return $group;
    }

    protected function storeView(
        int $id,
        string $code,
        string $name,
        int $websiteId,
        int $groupId,
        bool $isActive = true
    ): StoreInterface {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($id);
        $store->method('getCode')->willReturn($code);
        $store->method('getName')->willReturn($name);
        $store->method('getWebsiteId')->willReturn($websiteId);
        $store->method('getStoreGroupId')->willReturn($groupId);
        $store->method('getIsActive')->willReturn($isActive);

        return $store;
    }
}
