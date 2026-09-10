<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Fakes;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;

/**
 * Where each module is registered from, which is not always the root of its composer package.
 */
class FakeComponentRegistrar implements ComponentRegistrarInterface
{
    /** @var array<string, array<string, string>> */
    private array $paths = [];

    public function withModulePath(string $moduleName, string $path): self
    {
        $this->paths[ComponentRegistrar::MODULE][$moduleName] = $path;

        return $this;
    }

    /**
     * @param string $type
     * @return array<string, string>
     */
    public function getPaths($type)
    {
        return $this->paths[$type] ?? [];
    }

    /**
     * @param string $type
     * @param string $componentName
     * @return string|null
     */
    public function getPath($type, $componentName)
    {
        return $this->paths[$type][$componentName] ?? null;
    }
}
