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
            . 'If you need information from the user (like an email address or value), ask for it. '
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

    public function getAccentColor(): string
    {
        return $this->getStoreValue(self::XML_PATH_ACCENT_COLOR) ?: '#E8710A';
    }

    public function getTextColor(): string
    {
        return $this->getStoreValue(self::XML_PATH_TEXT_COLOR) ?: '#FFFFFF';
    }

    public function getAssistantName(): string
    {
        return $this->getStoreValue(self::XML_PATH_ASSISTANT_NAME) ?: 'Maggy';
    }

    public function getLanguage(): string
    {
        return $this->getStoreValue(self::XML_PATH_LANGUAGE) ?: 'auto';
    }

    public function getInternalUrl(): string
    {
        return trim((string)$this->getStoreValue(self::XML_PATH_INTERNAL_URL));
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
