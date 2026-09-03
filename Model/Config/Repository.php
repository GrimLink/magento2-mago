<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use MaggyAssistant\Base\Api\Config\RepositoryInterface as ConfigRepositoryInterface;

class Repository extends System\BaseRepository implements ConfigRepositoryInterface
{
    public function getExtensionVersion(): string
    {
        return 'v' . ($this->getComposerData()['version'] ?? '0.0.0');
    }

    public function getMagentoVersion(): string
    {
        return $this->metadata->getVersion();
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->isSetFlag(self::XML_PATH_EXTENSION_ENABLE, $storeId);
    }

    public function getSupportLink(): string
    {
        return '';
    }

    public function getExtensionCode(): string
    {
        return self::EXTENSION_CODE;
    }

    public function isDebugEnabled(): bool
    {
        return $this->isSetFlag(self::XML_PATH_DEBUG);
    }

    public function getPayloadRetentionDays(): int
    {
        // Non-numeric/negative config falls back to 30 rather than failing open to keep-forever
        $value = $this->getStoreValue(self::XML_PATH_PAYLOAD_RETENTION_DAYS);
        return ctype_digit($value) ? (int)$value : 30;
    }

    public function getAiServiceId(): string
    {
        return trim((string)$this->getStoreValue(self::XML_PATH_AI_SERVICE));
    }

    public function getMaxTokens(): int
    {
        return (int)($this->getStoreValue(self::XML_PATH_MAX_TOKENS) ?: 4096);
    }

    public function isStreamingEnabled(): bool
    {
        return $this->isSetFlag(self::XML_PATH_STREAMING);
    }

    public function getSystemPrompt(): string
    {
        $custom = $this->getStoreValue(self::XML_PATH_SYSTEM_PROMPT);
        $base = 'Today is ' . date('Y-m-d') . '. '
            . 'You are a Magento store assistant with tools to take direct action. '
            . 'IMPORTANT: Always USE your available tools to fulfill requests. Never tell the user to do something manually '
            . 'when you have a tool that can do it. '
            . 'NEVER ask the user for confirmation before using a tool. Just call the tool directly. '
            . 'Write actions are automatically intercepted by the system and shown to the user for confirmation '
            . 'before execution — you do not need to handle this yourself. '
            . 'If you need information from the user (like an email address, a value, or which website or store view '
            . 'a change applies to), ask for it; asking for missing input is not asking for confirmation. '
            . 'But once you have all the information, call the tool immediately without asking "shall I proceed?". '
            . 'You ONLY help with Magento-related topics: store management, products, orders, customers, '
            . 'configuration, extensions, and troubleshooting. '
            . 'If a question is not related to Magento or e-commerce store management, politely decline. '
            . 'Be concise and actionable.';

        $language = $this->getLanguage();
        if ($language === 'auto') {
            $base .= ' Respond in the same language as the user.';
        } else {
            $base .= ' IMPORTANT: You MUST always respond in ' . $language . ', regardless of what language the user writes in.';
        }

        return $custom ? $base . "\n\n" . $custom : $base;
    }

    public function getMaxToolIterations(): int
    {
        return (int)($this->getStoreValue(self::XML_PATH_MAX_TOOL_ITERATIONS) ?: 10);
    }

    public function getMaxResponseTokens(): int
    {
        return (int)($this->getStoreValue(self::XML_PATH_MAX_RESPONSE_TOKENS) ?: 4000);
    }

    public function getAccentColor(): string
    {
        return $this->getStoreValue(self::XML_PATH_ACCENT_COLOR) ?: '#F26322';
    }

    public function getTextColor(): string
    {
        return $this->getStoreValue(self::XML_PATH_TEXT_COLOR) ?: '#FFFFFF';
    }

    public function getAssistantName(): string
    {
        return $this->getStoreValue(self::XML_PATH_ASSISTANT_NAME) ?: 'Mago';
    }

    public function getLanguage(): string
    {
        return $this->getStoreValue(self::XML_PATH_LANGUAGE) ?: 'auto';
    }

    public function getInternalUrl(): string
    {
        return trim((string)$this->getStoreValue(self::XML_PATH_INTERNAL_URL));
    }

    public function isInternalSslVerifyEnabled(): bool
    {
        return $this->isSetFlag(self::XML_PATH_INTERNAL_SSL_VERIFY);
    }

    public function isDocsEnabled(): bool
    {
        return $this->isSetFlag(self::XML_PATH_DOCS_ENABLED, null, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
    }

    public function getDocsSourceRepo(): string
    {
        $value = trim($this->getStoreValue(self::XML_PATH_DOCS_SOURCE_REPO, null, ScopeConfigInterface::SCOPE_TYPE_DEFAULT));
        return $value !== '' ? $value : 'mage-os/mirror-commerce-admin.en';
    }

    public function getDocsRef(): string
    {
        $value = trim($this->getStoreValue(self::XML_PATH_DOCS_REF, null, ScopeConfigInterface::SCOPE_TYPE_DEFAULT));
        return $value !== '' ? $value : 'main';
    }

    public function getDocsTopK(): int
    {
        return (int)($this->getStoreValue(self::XML_PATH_DOCS_TOP_K, null, ScopeConfigInterface::SCOPE_TYPE_DEFAULT) ?: 5);
    }
}
