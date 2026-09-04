<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Service\Store;

use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Knows the website / store group / store view layout of this installation.
 *
 * Every chat request gets a summary of this layout in its system prompt so the assistant can tell
 * whether a request targets the default scope or a specific website or store view before it acts.
 * Scope-sensitive tools use the same object to validate the scope they were handed and to describe
 * it back to the user, so an invalid or guessed id never reaches Magento.
 */
class StoreScopeContext
{
    public const SCOPE_DEFAULT = 'default';
    public const SCOPE_WEBSITES = 'websites';
    public const SCOPE_STORES = 'stores';

    /** Store code the REST API understands as "all store views" (admin store, id 0) */
    public const REST_ALL_STORES_CODE = 'all';

    /** @var array<int, array{id:int,code:string,name:string,is_default:bool,groups:array<int,array{id:int,name:string,is_default:bool,stores:array<int,array{id:int,code:string,name:string,is_default:bool,is_active:bool}>}>}>|null */
    private ?array $websites = null;

    /** @var array<int, array{id:int,code:string,name:string,is_default:bool,is_active:bool,website_id:int,website_name:string}>|null */
    private ?array $storeViews = null;

    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Websites with their store groups and store views, admin scope excluded.
     *
     * @return array<int, array{id:int,code:string,name:string,is_default:bool,groups:array<int,array{id:int,name:string,is_default:bool,stores:array<int,array{id:int,code:string,name:string,is_default:bool,is_active:bool}>}>}>
     */
    public function getWebsites(): array
    {
        if ($this->websites !== null) {
            return $this->websites;
        }

        $defaultStore = $this->getDefaultStoreView();
        $defaultStoreId = $defaultStore ? (int)$defaultStore->getId() : 0;
        $defaultWebsiteId = $defaultStore ? (int)$defaultStore->getWebsiteId() : 0;

        $storesByGroup = [];
        foreach ($this->storeManager->getStores() as $store) {
            $storesByGroup[(int)$store->getStoreGroupId()][(int)$store->getId()] = [
                'id' => (int)$store->getId(),
                'code' => (string)$store->getCode(),
                'name' => (string)$store->getName(),
                'is_default' => (int)$store->getId() === $defaultStoreId,
                'is_active' => (bool)$store->getIsActive(),
            ];
        }

        $groupsByWebsite = [];
        foreach ($this->storeManager->getGroups() as $group) {
            $groupsByWebsite[(int)$group->getWebsiteId()][(int)$group->getId()] = [
                'id' => (int)$group->getId(),
                'name' => (string)$group->getName(),
                'is_default' => false,
                'stores' => $storesByGroup[(int)$group->getId()] ?? [],
            ];
        }

        $this->websites = [];
        foreach ($this->storeManager->getWebsites() as $website) {
            $websiteId = (int)$website->getId();
            $groups = $groupsByWebsite[$websiteId] ?? [];
            foreach ($groups as $groupId => $group) {
                $groups[$groupId]['is_default'] = $groupId === (int)$website->getDefaultGroupId();
            }
            $this->websites[$websiteId] = [
                'id' => $websiteId,
                'code' => (string)$website->getCode(),
                'name' => (string)$website->getName(),
                'is_default' => $websiteId === $defaultWebsiteId,
                'groups' => $groups,
            ];
        }

        return $this->websites;
    }

    /**
     * Flat list of store views keyed by id, admin store excluded.
     *
     * @return array<int, array{id:int,code:string,name:string,is_default:bool,is_active:bool,website_id:int,website_name:string}>
     */
    public function getStoreViews(): array
    {
        if ($this->storeViews !== null) {
            return $this->storeViews;
        }

        $this->storeViews = [];
        foreach ($this->getWebsites() as $website) {
            foreach ($website['groups'] as $group) {
                foreach ($group['stores'] as $store) {
                    $this->storeViews[$store['id']] = $store + [
                        'website_id' => $website['id'],
                        'website_name' => $website['name'],
                    ];
                }
            }
        }

        return $this->storeViews;
    }

    /**
     * Whether there is only one store view, so scope never needs to be verified with the user.
     */
    public function hasSingleStoreView(): bool
    {
        return count($this->getStoreViews()) <= 1;
    }

    public function hasWebsite(int $websiteId): bool
    {
        return isset($this->getWebsites()[$websiteId]);
    }

    public function hasStoreView(int $storeId): bool
    {
        return isset($this->getStoreViews()[$storeId]);
    }

    /**
     * Validate a scope / scope id pair as accepted by config tools.
     *
     * @return string|null Error message for the model, null when the pair is valid
     */
    public function validateScope(string $scope, int $scopeId): ?string
    {
        switch ($scope) {
            case self::SCOPE_DEFAULT:
                return $scopeId === 0
                    ? null
                    : 'Scope "default" always uses scope_id 0. Use scope "websites" or "stores" to target a website or store view.';
            case self::SCOPE_WEBSITES:
                return $this->hasWebsite($scopeId)
                    ? null
                    : sprintf('Unknown website id %d. Available websites: %s.', $scopeId, $this->listWebsites());
            case self::SCOPE_STORES:
                return $this->hasStoreView($scopeId)
                    ? null
                    : sprintf('Unknown store view id %d. Available store views: %s.', $scopeId, $this->listStoreViews());
            default:
                return sprintf('Unknown scope "%s". Use "default", "websites" or "stores".', $scope);
        }
    }

    /**
     * Human-readable label for a scope / scope id pair, e.g. 'store view "Luma" (id 2, code "luma")'.
     */
    public function describeScope(string $scope, int $scopeId): string
    {
        if ($scope === self::SCOPE_WEBSITES && $this->hasWebsite($scopeId)) {
            $website = $this->getWebsites()[$scopeId];
            return sprintf('website "%s" (id %d, code "%s")', $website['name'], $website['id'], $website['code']);
        }
        if ($scope === self::SCOPE_STORES && $this->hasStoreView($scopeId)) {
            $store = $this->getStoreViews()[$scopeId];
            return sprintf('store view "%s" (id %d, code "%s")', $store['name'], $store['id'], $store['code']);
        }
        if ($scope === self::SCOPE_DEFAULT) {
            return 'default scope (applies to every website and store view without an override)';
        }

        return sprintf('%s scope id %d', $scope, $scopeId);
    }

    /**
     * Label for a store view target where 0 means "all store views".
     */
    public function describeStoreTarget(int $storeId): string
    {
        return $storeId === 0 ? 'all store views' : $this->describeScope(self::SCOPE_STORES, $storeId);
    }

    /**
     * Store code to put in an internal REST URL so Magento runs the call in that store view.
     *
     * @return string|null 'all' for 0, the store view code otherwise, null for an unknown id
     */
    public function getRestStoreCode(int $storeId): ?string
    {
        if ($storeId === 0) {
            return self::REST_ALL_STORES_CODE;
        }

        return $this->hasStoreView($storeId) ? $this->getStoreViews()[$storeId]['code'] : null;
    }

    /**
     * Error message for a store view id that is neither 0 nor an existing store view.
     */
    public function getUnknownStoreViewError(int $storeId): string
    {
        return sprintf(
            'Unknown store view id %d. Use 0 for all store views or one of: %s.',
            $storeId,
            $this->listStoreViews()
        );
    }

    /**
     * Store layout plus scope rules, appended to the system prompt of every chat request.
     */
    public function toPromptSection(): string
    {
        $storeViews = $this->getStoreViews();

        if ($storeViews === []) {
            return '[Store scope] No store views are configured. Apply configuration at the default scope.';
        }

        if ($this->hasSingleStoreView()) {
            $store = reset($storeViews);
            return sprintf(
                '[Store scope] This installation has a single store view: "%s" (id %d, code "%s") on website "%s" (id %d). '
                . 'Apply configuration and content at the default scope and never ask the user which store view to use.',
                $store['name'],
                $store['id'],
                $store['code'],
                $store['website_name'],
                $store['website_id']
            );
        }

        $lines = [];
        $lines[] = sprintf(
            '[Store scope] This installation has %d website%s and %d store views. Configuration and content can be '
            . 'set at the default (global) scope, per website, or per store view; a deeper scope overrides the one above it.',
            count($this->getWebsites()),
            count($this->getWebsites()) === 1 ? '' : 's',
            count($storeViews)
        );

        foreach ($this->getWebsites() as $website) {
            $lines[] = sprintf(
                '- Website "%s" (id %d, code "%s"%s)',
                $website['name'],
                $website['id'],
                $website['code'],
                $website['is_default'] ? ', default' : ''
            );
            foreach ($website['groups'] as $group) {
                $lines[] = sprintf('  - Store group "%s" (id %d)', $group['name'], $group['id']);
                foreach ($group['stores'] as $store) {
                    $lines[] = sprintf(
                        '    - Store view "%s" (id %d, code "%s"%s%s)',
                        $store['name'],
                        $store['id'],
                        $store['code'],
                        $store['is_default'] ? ', default' : '',
                        $store['is_active'] ? '' : ', disabled'
                    );
                }
            }
        }

        $lines[] = 'Scope rules for every request:';
        $lines[] = '1. Work out which scope the request targets: default (all store views), a website, or one store view. '
            . 'Match names or codes the user mentions to the list above and use that id; never invent an id.';
        $lines[] = '2. Before a WRITE on scope-sensitive data (configuration, CMS pages and blocks, product content): '
            . 'if the user did not name a scope and the change could reasonably differ per store view, ask which scope '
            . 'to use before calling the tool. This is collecting missing input, not asking for confirmation. '
            . 'When the user clearly means the whole shop, use the default scope.';
        $lines[] = '3. For READS use the default scope unless a scope is named, and tell the user when a website or '
            . 'store view overrides the value.';
        $lines[] = '4. Always state the scope you used in your answer, e.g. "default scope, all store views" or '
            . '"store view Luma".';

        return implode("\n", $lines);
    }

    private function getDefaultStoreView(): ?StoreInterface
    {
        try {
            return $this->storeManager->getDefaultStoreView();
        } catch (\Throwable) {
            return null;
        }
    }

    private function listWebsites(): string
    {
        $parts = [];
        foreach ($this->getWebsites() as $website) {
            $parts[] = sprintf('"%s" (id %d)', $website['name'], $website['id']);
        }

        return $parts === [] ? 'none' : implode(', ', $parts);
    }

    private function listStoreViews(): string
    {
        $parts = [];
        foreach ($this->getStoreViews() as $store) {
            $parts[] = sprintf('"%s" (id %d, code "%s")', $store['name'], $store['id'], $store['code']);
        }

        return $parts === [] ? 'none' : implode(', ', $parts);
    }
}
