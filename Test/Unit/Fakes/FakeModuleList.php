<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Fakes;

use Magento\Framework\Module\ModuleListInterface;

/**
 * The enabled modules of a store, which is a subset of the declared ones.
 */
class FakeModuleList implements ModuleListInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $enabled = [];

    public function withEnabledModule(string $moduleName): self
    {
        $this->enabled[$moduleName] = ['name' => $moduleName];

        return $this;
    }

    public function getAll()
    {
        return $this->enabled;
    }

    public function getOne($name)
    {
        return $this->enabled[$name] ?? null;
    }

    public function getNames()
    {
        return array_keys($this->enabled);
    }

    public function has($name)
    {
        return isset($this->enabled[$name]);
    }
}
