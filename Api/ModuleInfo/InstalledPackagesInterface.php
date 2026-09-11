<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Api\ModuleInfo;

/**
 * Composer's runtime record of what it installed, wrapped so it can be faked in tests
 * @api
 */
interface InstalledPackagesInterface
{
    /**
     * @param string $packageName
     * @return string The installed version, or '' when Composer does not know the package
     */
    public function getVersion(string $packageName): string;

    /**
     * The package a directory belongs to, for modules that register themselves from a subdirectory
     * of their package and are therefore invisible to Magento's own composer.json reader.
     *
     * @param string $path
     * @return string The package name, or '' when the path sits outside every installed package
     */
    public function findPackageByPath(string $path): string;
}
