<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Model\ModuleInfo;

use Magento\Framework\Module\ModuleList\Loader as ModuleDeclarationLoader;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Module\PackageInfo;
use MaggyAssistant\Base\Api\ModuleInfo\RepositoryInterface;

class Repository implements RepositoryInterface
{
    private ?array $moduleDeclarations = null;

    public function __construct(
        private readonly PackageInfo $packageInfo,
        private readonly ModuleDeclarationLoader $moduleDeclarationLoader,
        private readonly ModuleListInterface $moduleList
    ) {
    }

    public function getAllModuleNames(): array
    {
        return array_keys($this->getModuleDeclarations());
    }

    public function findModuleNames(string $query): array
    {
        $words = $this->tokenize($query);
        if ($words === []) {
            return [];
        }

        $exactMatch = $this->findExactMatch($words);
        if ($exactMatch !== null) {
            return [$exactMatch];
        }

        return array_values(array_filter(
            $this->getAllModuleNames(),
            fn (string $moduleName): bool => $this->matchesAllWords($moduleName, $words)
        ));
    }

    /**
     * A module whose own name normalizes to exactly the query wins outright, so a query like
     * "Magento Catalog" resolves straight to Magento_Catalog instead of every module whose name
     * or package happens to contain the word "catalog".
     *
     * @param string[] $words
     */
    private function findExactMatch(array $words): ?string
    {
        $normalizedQuery = implode(' ', $words);
        foreach ($this->getAllModuleNames() as $moduleName) {
            if (implode(' ', $this->tokenize($moduleName)) === $normalizedQuery) {
                return $moduleName;
            }
        }

        return null;
    }

    public function getPackageName(string $moduleName): string
    {
        return (string)$this->packageInfo->getPackageName($moduleName);
    }

    public function isEnabled(string $moduleName): bool
    {
        return $this->moduleList->has($moduleName);
    }

    public function getVersionInfo(string $moduleName): array
    {
        $composerVersion = (string)$this->packageInfo->getVersion($moduleName);
        if ($composerVersion !== '') {
            return ['version' => $composerVersion, 'source' => self::VERSION_SOURCE_COMPOSER];
        }

        $setupVersion = (string)($this->getModuleDeclarations()[$moduleName]['setup_version'] ?? '');
        if ($setupVersion !== '') {
            return ['version' => $setupVersion, 'source' => self::VERSION_SOURCE_MODULE_XML];
        }

        return ['version' => '', 'source' => self::VERSION_SOURCE_UNKNOWN];
    }

    /**
     * @param string[] $words
     */
    private function matchesAllWords(string $moduleName, array $words): bool
    {
        $haystack = implode(' ', $this->tokenize($moduleName . ' ' . $this->getPackageName($moduleName)));
        foreach ($words as $word) {
            if (!str_contains($haystack, $word)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string[]
     */
    private function tokenize(string $value): array
    {
        $normalized = strtolower(str_replace(['_', '-', '/'], ' ', $value));

        return array_values(array_filter(explode(' ', $normalized), static fn (string $word): bool => $word !== ''));
    }

    private function getModuleDeclarations(): array
    {
        if ($this->moduleDeclarations === null) {
            $this->moduleDeclarations = $this->moduleDeclarationLoader->load();
        }

        return $this->moduleDeclarations;
    }
}
