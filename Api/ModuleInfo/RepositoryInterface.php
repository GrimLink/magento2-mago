<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Api\ModuleInfo;

/**
 * Installed module lookup for the module_version skill
 * @api
 */
interface RepositoryInterface
{
    public const VERSION_SOURCE_COMPOSER = 'composer';
    public const VERSION_SOURCE_MODULE_XML = 'module.xml';
    public const VERSION_SOURCE_UNKNOWN = 'unknown';

    /**
     * Every registered module name, regardless of enabled state
     *
     * @return string[]
     */
    public function getAllModuleNames(): array;

    /**
     * Module names whose name or composer package matches every word of the given free-text query
     *
     * @param string $query
     * @return string[]
     */
    public function findModuleNames(string $query): array;

    /**
     * @param string $moduleName
     * @return string Composer package name, or '' when unknown
     */
    public function getPackageName(string $moduleName): string;

    /**
     * @param string $moduleName
     * @return bool
     */
    public function isEnabled(string $moduleName): bool;

    /**
     * The installed version, preferring the composer.json "version" field and falling back to
     * module.xml's setup_version for modules that ship without a composer release version
     * (the common case for in-house app/code modules).
     *
     * @param string $moduleName
     * @return array{version: string, source: string} source is one of the VERSION_SOURCE_* constants
     */
    public function getVersionInfo(string $moduleName): array;
}
