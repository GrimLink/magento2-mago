<?php
/**
 * Copyright © Maggy Assistant
 */
declare(strict_types=1);

namespace MaggyAssistant\Base\Api\Ai;

/**
 * AI Provider interface — strategy pattern
 * @api
 */
interface ProviderInterface
{
    /**
     * Send a chat request and return the full response
     *
     * @param array $messages
     * @param array $tools
     * @param array $options
     * @return array
     */
    public function chat(array $messages, array $tools = [], array $options = []): array;

    /**
     * Send a streaming chat request, yielding chunks via callback
     *
     * @param array $messages
     * @param array $tools
     * @param array $options
     * @param callable $onChunk fn(string $type, array $data)
     * @return array Final assembled response
     */
    public function stream(array $messages, array $tools = [], array $options = [], ?callable $onChunk = null): array;

    /**
     * @return string
     */
    public function getProviderName(): string;
}
