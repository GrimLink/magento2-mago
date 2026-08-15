<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Api\Config;

use Magento\Store\Api\Data\StoreInterface;

/**
 * Config repository interface
 * @api
 */
interface RepositoryInterface
{
    public const EXTENSION_CODE = 'MaggyAssistant_Base';
    public const XML_PATH_EXTENSION_ENABLE = 'maggy/general/enabled';
    public const XML_PATH_DEBUG = 'maggy/debug/debug';
    public const XML_PATH_PAYLOAD_RETENTION_DAYS = 'maggy/debug/payload_retention_days';
    public const XML_PATH_PROVIDER = 'maggy/api/provider';
    public const XML_PATH_CLAUDE_API_KEY = 'maggy/api/claude_api_key';
    public const XML_PATH_CLAUDE_MODEL = 'maggy/api/claude_model';
    public const XML_PATH_OPENAI_API_KEY = 'maggy/api/openai_api_key';
    public const XML_PATH_OPENAI_MODEL = 'maggy/api/openai_model';
    public const XML_PATH_MAX_TOKENS = 'maggy/api/max_tokens';
    public const XML_PATH_TEMPERATURE = 'maggy/api/temperature';
    public const XML_PATH_STREAMING = 'maggy/api/streaming';
    public const XML_PATH_SYSTEM_PROMPT = 'maggy/chat/system_prompt';
    public const XML_PATH_MAX_TOOL_ITERATIONS = 'maggy/chat/max_tool_iterations';
    public const XML_PATH_ACCENT_COLOR = 'maggy/chat/accent_color';
    public const XML_PATH_TEXT_COLOR = 'maggy/chat/text_color';
    public const XML_PATH_ASSISTANT_NAME = 'maggy/chat/assistant_name';
    public const XML_PATH_INTERNAL_URL = 'maggy/api/internal_url';
    public const XML_PATH_LANGUAGE = 'maggy/chat/language';
    public const XML_PATH_DOCS_ENABLED = 'maggy/docs/enabled';
    public const XML_PATH_DOCS_SOURCE_REPO = 'maggy/docs/source_repo';
    public const XML_PATH_DOCS_REF = 'maggy/docs/ref';
    public const XML_PATH_DOCS_TOP_K = 'maggy/docs/top_k';
    /**
     * @return string
     */
    public function getExtensionVersion(): string;

    /**
     * @return string
     */
    public function getExtensionCode(): string;

    /**
     * @return string
     */
    public function getMagentoVersion(): string;

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool;

    /**
     * @param int|null $storeId
     * @return StoreInterface
     */
    public function getStore(?int $storeId = null): StoreInterface;

    /**
     * @return string
     */
    public function getSupportLink(): string;

    /**
     * @return bool
     */
    public function isDebugEnabled(): bool;

    /**
     * @return int
     */
    public function getPayloadRetentionDays(): int;

    /**
     * @return string
     */
    public function getProvider(): string;

    /**
     * @return string
     */
    public function getApiKey(): string;

    /**
     * @return string
     */
    public function getModel(): string;

    /**
     * @return int
     */
    public function getMaxTokens(): int;

    /**
     * @return float
     */
    public function getTemperature(): float;

    /**
     * @return bool
     */
    public function isStreamingEnabled(): bool;

    /**
     * @return string
     */
    public function getSystemPrompt(): string;

    /**
     * @return int
     */
    public function getMaxToolIterations(): int;

    /**
     * @return string
     */
    public function getAccentColor(): string;

    /**
     * @return string
     */
    public function getTextColor(): string;

    /**
     * @return string
     */
    public function getAssistantName(): string;

    /**
     * @return string
     */
    public function getLanguage(): string;

    /**
     * @return string
     */
    public function getInternalUrl(): string;

    /**
     * @return bool
     */
    public function isDocsEnabled(): bool;

    /**
     * @return string
     */
    public function getDocsSourceRepo(): string;

    /**
     * @return string
     */
    public function getDocsRef(): string;

    /**
     * @return int
     */
    public function getDocsTopK(): int;
}
