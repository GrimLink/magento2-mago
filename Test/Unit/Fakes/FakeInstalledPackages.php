<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Fakes;

use MagoAssistant\Mago\Api\ModuleInfo\InstalledPackagesInterface;

/**
 * What Composer installed: a package name, the version it resolved to, and the directory it owns.
 */
class FakeInstalledPackages implements InstalledPackagesInterface
{
    /** @var array<string, array{version: string, install_path: string}> */
    private array $packages = [];

    public function withPackage(string $packageName, string $version, string $installPath = ''): self
    {
        $this->packages[$packageName] = ['version' => $version, 'install_path' => $installPath];

        return $this;
    }

    public function getVersion(string $packageName): string
    {
        return $this->packages[$packageName]['version'] ?? '';
    }

    public function findPackageByPath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        foreach ($this->packages as $packageName => $package) {
            $installPath = $package['install_path'];
            if ($installPath !== '' && ($path === $installPath || str_starts_with($path, $installPath . '/'))) {
                return $packageName;
            }
        }

        return '';
    }
}
