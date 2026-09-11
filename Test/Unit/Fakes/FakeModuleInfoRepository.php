<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Fakes;

use MagoAssistant\Mago\Api\ModuleInfo\RepositoryInterface;

class FakeModuleInfoRepository implements RepositoryInterface
{
    /** @var array<string, array{package_name: string, version: string, source: string, is_enabled: bool}> */
    private array $modules = [];

    public function withModule(
        string $moduleName,
        string $packageName = '',
        string $version = '',
        string $source = self::VERSION_SOURCE_UNKNOWN,
        bool $isEnabled = true
    ): self {
        $this->modules[$moduleName] = [
            'package_name' => $packageName,
            'version' => $version,
            'source' => $source,
            'is_enabled' => $isEnabled,
        ];

        return $this;
    }

    public function getAllModuleNames(): array
    {
        return array_keys($this->modules);
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
        return $this->modules[$moduleName]['package_name'] ?? '';
    }

    public function isEnabled(string $moduleName): bool
    {
        return $this->modules[$moduleName]['is_enabled'] ?? false;
    }

    public function getVersionInfo(string $moduleName): array
    {
        if (!isset($this->modules[$moduleName])) {
            return ['version' => '', 'source' => self::VERSION_SOURCE_UNKNOWN];
        }

        return [
            'version' => $this->modules[$moduleName]['version'],
            'source' => $this->modules[$moduleName]['source'],
        ];
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
}
