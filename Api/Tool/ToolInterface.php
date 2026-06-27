<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Api\Tool;

/**
 * Tool interface — each tool the AI can invoke
 * @api
 */
interface ToolInterface
{
    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @return string
     */
    public function getDescription(): string;

    /**
     * JSON Schema for tool parameters
     *
     * @return array
     */
    public function getParameterSchema(): array;

    /**
     * Execute the tool with given parameters
     *
     * @param array $params
     * @return array
     */
    public function execute(array $params): array;

    /**
     * Whether this tool only reads data (no side effects)
     *
     * @return bool
     */
    public function isReadOnly(): bool;

    /**
     * ACL resource required to use this tool
     *
     * @return string
     */
    public function getRequiredAcl(): string;

    /**
     * Detailed instructions injected only when this tool is called (JIT).
     * Return empty string if no extra instructions needed.
     *
     * @return string
     */
    public function getInstructions(): string;

    /**
     * Whether a specific invocation is read-only based on input parameters.
     * For tools with mixed read/write sub-actions (e.g. cms_data),
     * this checks the actual action being called.
     *
     * @param array $input The tool call input parameters
     * @return bool
     */
    public function isReadOnlyAction(array $input): bool;

    /**
     * Native Magento ACL resource required to use this tool.
     * Return empty string if no additional Magento ACL check is needed
     * beyond the MaggyAssistant ACL from getRequiredAcl().
     *
     * Examples: 'Magento_Backend::cache', 'Magento_Indexer::changeMode'
     *
     * @return string
     */
    public function getMagentoAcl(): string;
}
