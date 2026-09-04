<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Test\Unit\Fakes;

use Magento\Framework\Module\ModuleList\Loader;

/**
 * The module.xml view of a store: every registered module, in declaration order.
 */
class FakeModuleDeclarationLoader extends Loader
{
    /** @var array<string, array<string, mixed>> */
    private array $declarations = [];

    /**
     * @SuppressWarnings(PHPMD.MissingParentCallInConstructor)
     */
    public function __construct()
    {
    }

    public function withModule(string $moduleName, string $setupVersion = ''): self
    {
        $declaration = ['name' => $moduleName, 'sequence' => []];
        if ($setupVersion !== '') {
            $declaration['setup_version'] = $setupVersion;
        }
        $this->declarations[$moduleName] = $declaration;

        return $this;
    }

    /**
     * @param array $exclude
     * @return array<string, array<string, mixed>>
     */
    public function load(array $exclude = [])
    {
        return array_diff_key($this->declarations, array_flip($exclude));
    }
}
