<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Model\Config;

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

    public function getProvider(): string
    {
        return $this->getStoreValue(self::XML_PATH_PROVIDER) ?: 'claude';
    }

    public function getApiKey(): string
    {
        $provider = $this->getProvider();
        $path = $provider === 'openai' ? self::XML_PATH_OPENAI_API_KEY : self::XML_PATH_CLAUDE_API_KEY;
        $encrypted = $this->getStoreValue($path);
        return $encrypted ? $this->encryptor->decrypt($encrypted) : '';
    }

    public function getModel(): string
    {
        $provider = $this->getProvider();
        $path = $provider === 'openai' ? self::XML_PATH_OPENAI_MODEL : self::XML_PATH_CLAUDE_MODEL;
        return $this->getStoreValue($path) ?: ($provider === 'openai' ? 'gpt-4o' : 'claude-sonnet-4-20250514');
    }

    public function getMaxTokens(): int
    {
        return (int)($this->getStoreValue(self::XML_PATH_MAX_TOKENS) ?: 4096);
    }

    public function getTemperature(): float
    {
        $value = $this->getStoreValue(self::XML_PATH_TEMPERATURE);
        return $value !== '' ? (float)$value : 0.7;
    }

    public function isStreamingEnabled(): bool
    {
        return $this->isSetFlag(self::XML_PATH_STREAMING);
    }

    public function getSystemPrompt(): string
    {
        $custom = $this->getStoreValue(self::XML_PATH_SYSTEM_PROMPT);
        $base = 'You are a Magento store assistant with tools to take direct action. '
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
            . 'Be concise and actionable. Respond in the same language as the user.';

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

    public function getInternalUrl(): string
    {
        return trim((string)$this->getStoreValue(self::XML_PATH_INTERNAL_URL));
    }
}
