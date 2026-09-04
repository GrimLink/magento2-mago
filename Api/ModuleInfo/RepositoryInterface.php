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
    public const VERSION_SOURCE_COMPOSER_JSON = 'composer.json';
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
     * The installed version, taken from Composer's own record of what it installed. Falls back to
     * the module composer.json "version" field, and then to module.xml's setup_version, for the
     * modules Composer does not know about (the common case for in-house app/code modules).
     *
     * @param string $moduleName
     * @return array{version: string, source: string} source is one of the VERSION_SOURCE_* constants
     */
    public function getVersionInfo(string $moduleName): array;
}
