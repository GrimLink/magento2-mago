<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Privacy;

use MagoAssistant\Mago\Api\Privacy\VaultStorageInterface;

/**
 * The reversible token map for one conversation (issue #97). A PII value is replaced with a stable,
 * type-carrying token on the way to the LLM and swapped back on the way to the admin. The same value
 * always yields the same token within the conversation, so a token emitted on one turn still resolves
 * when the conversation history is replayed on the next.
 *
 * With a VaultStorageInterface bound to a conversation (beginConversation), the map survives across
 * requests: a token minted last turn, or before a confirmed write ran in its own request, still
 * resolves. Without storage (or an unbound vault) it is request-scoped memory. Storage failure is
 * silent — the vault just degrades to request scope.
 */
class ConversationVault
{
    /** @var array<string,string> "type\0value" => token */
    private array $tokenByValue = [];

    /** @var array<string,string> token => original value */
    private array $valueByToken = [];

    /** @var array<string,int> type => highest issued number */
    private array $counters = [];

    private ?int $conversationId = null;

    public function __construct(
        private readonly ?VaultStorageInterface $storage = null
    ) {
    }

    /**
     * Bind the vault to a conversation and load its existing tokens, so numbering continues and
     * earlier turns' tokens resolve. Safe to call once per request; storage errors are swallowed.
     */
    public function beginConversation(int $conversationId): void
    {
        $this->conversationId = $conversationId;
        if ($this->storage === null) {
            return;
        }

        foreach ($this->storage->loadForConversation($conversationId) as $row) {
            $this->remember($row['type'], $row['value'], $row['token']);
        }
    }

    public function tokenise(string $value, string $type): string
    {
        if ($value === '') {
            return $value;
        }

        $key = $type . "\0" . $value;
        if (isset($this->tokenByValue[$key])) {
            return $this->tokenByValue[$key];
        }

        $number = ($this->counters[$type] ?? 0) + 1;
        $token = sprintf('[%s_%d]', $type, $number);
        $this->remember($type, $value, $token);

        if ($this->conversationId !== null && $this->storage !== null) {
            $this->storage->persist($this->conversationId, $token, $value, $type);
        }

        return $token;
    }

    public function rehydrate(string $text): string
    {
        if ($this->valueByToken === []) {
            return $text;
        }

        return strtr($text, $this->valueByToken);
    }

    public function has(string $token): bool
    {
        return isset($this->valueByToken[$token]);
    }

    /**
     * Register a token↔value pair and keep the per-type counter ahead of any number already issued,
     * whether it was just minted or loaded from storage.
     */
    private function remember(string $type, string $value, string $token): void
    {
        $this->tokenByValue[$type . "\0" . $value] = $token;
        $this->valueByToken[$token] = $value;

        if (preg_match('/_(\d+)\]$/', $token, $m) === 1) {
            $this->counters[$type] = max($this->counters[$type] ?? 0, (int)$m[1]);
        }
    }
}
