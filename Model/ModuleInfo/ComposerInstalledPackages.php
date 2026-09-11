<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Model\ModuleInfo;

use Composer\InstalledVersions;
use MagoAssistant\Mago\Api\ModuleInfo\InstalledPackagesInterface;

class ComposerInstalledPackages implements InstalledPackagesInterface
{
    /** @var array<string, string>|null */
    private ?array $packagesByInstallPath = null;

    public function getVersion(string $packageName): string
    {
        if ($packageName === '' || !InstalledVersions::isInstalled($packageName)) {
            return '';
        }

        return (string)InstalledVersions::getPrettyVersion($packageName);
    }

    public function findPackageByPath(string $path): string
    {
        $modulePath = $this->normalize($path);
        if ($modulePath === '') {
            return '';
        }

        $candidates = array_filter(
            $this->getPackagesByInstallPath(),
            fn (string $packageName, string $installPath): bool => $this->contains($installPath, $modulePath),
            ARRAY_FILTER_USE_BOTH
        );
        if ($candidates === []) {
            return '';
        }

        $installPaths = array_keys($candidates);
        usort($installPaths, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $candidates[$installPaths[0]];
    }

    /**
     * A module directory belongs to a package when it is that package's own directory or sits inside it.
     */
    private function contains(string $installPath, string $modulePath): bool
    {
        return $modulePath === $installPath || str_starts_with($modulePath, $installPath . '/');
    }

    /**
     * @return array<string, string> Install path to package name
     */
    private function getPackagesByInstallPath(): array
    {
        if ($this->packagesByInstallPath === null) {
            $this->packagesByInstallPath = [];
            foreach (InstalledVersions::getInstalledPackages() as $packageName) {
                $installPath = $this->normalize((string)InstalledVersions::getInstallPath($packageName));
                if ($installPath !== '') {
                    $this->packagesByInstallPath[$installPath] = $packageName;
                }
            }
        }

        return $this->packagesByInstallPath;
    }

    private function normalize(string $path): string
    {
        return $path === '' ? '' : (string)realpath($path);
    }
}
