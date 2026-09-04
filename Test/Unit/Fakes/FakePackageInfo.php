<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Test\Unit\Fakes;

use Magento\Framework\Module\PackageInfo;

/**
 * The composer.json view of a module: its package name and the optional "version" field it declares.
 */
class FakePackageInfo extends PackageInfo
{
    /** @var array<string, array{package_name: string, version: string}> */
    private array $modules = [];

    /**
     * @SuppressWarnings(PHPMD.MissingParentCallInConstructor)
     */
    public function __construct()
    {
    }

    public function withModule(string $moduleName, string $packageName, string $declaredVersion = ''): self
    {
        $this->modules[$moduleName] = ['package_name' => $packageName, 'version' => $declaredVersion];

        return $this;
    }

    /**
     * @param string $moduleName
     * @return string
     */
    public function getPackageName($moduleName)
    {
        return $this->modules[$moduleName]['package_name'] ?? '';
    }

    /**
     * @param string $moduleName
     * @return string
     */
    public function getVersion($moduleName)
    {
        return $this->modules[$moduleName]['version'] ?? '';
    }
}
