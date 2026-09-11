<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Privacy;

/**
 * The privacy-mode entry point ChatService talks to (issue #97). One request-scoped instance holds
 * the conversation vault, so a value tokenised while filtering a tool result reads back as the same
 * token everywhere in the request. Magento shares one instance of a non-virtual type per request by
 * default, which is what makes that hold.
 *
 * V1 scope: rehydrate() exists for the eventual admin-facing display pass (per #97 decision 4 that
 * is client-side, so tokens split across SSE chunks still resolve) but is not wired into the stream
 * yet — the admin currently sees the tokens in the reply, which #97 §8 accepts for bare-id tokens.
 * The vault is request-scoped; cross-turn history replay and confirmed writes (a separate request)
 * need a vault persisted per conversation — a documented follow-up, guarded meanwhile by
 * containsToken() refusing a write that still carries a token.
 */
class PrivacyService
{
    public function __construct(
        private readonly PrivacyFilter $filter,
        private readonly ConversationVault $vault,
        private readonly PiiHeuristic $heuristic
    ) {
    }

    /**
     * Bind the vault to the current conversation so tokens persist and resolve across turns and the
     * confirmed-write round-trip. Called once at the start of a chat request.
     */
    public function beginConversation(int $conversationId): void
    {
        $this->vault->beginConversation($conversationId);
    }

    /**
     * Filter a tool result before it reaches the LLM. $action is the skill action (or the tool name
     * when there is no action), matching how the classification is keyed.
     *
     * @param array<string,mixed> $result
     * @return array<string,mixed>
     */
    public function filterToolResult(string $action, array $result): array
    {
        return $this->filter->filter($action, $result);
    }

    /**
     * Scrub free text in the outbound messages just before they reach the LLM: the admin's typed
     * message, replayed history and the custom system prompt all pass through here. Tool results are
     * already filtered upstream, so the heuristic finds nothing new in them (the pass is idempotent).
     * This is the one place that covers PII the admin types, which no field classification can catch.
     *
     * @param array<int,array<string,mixed>> $messages
     * @return array<int,array<string,mixed>>
     */
    public function scrubMessages(array $messages): array
    {
        foreach ($messages as $index => $message) {
            if (is_string($message['content'] ?? null) && $message['content'] !== '') {
                $messages[$index]['content'] = $this->heuristic->tokeniseFreeText($message['content'], $this->vault);
            }
        }

        return $messages;
    }

    /**
     * Rehydrate tokens in tool-call arguments before the tool runs. The model only ever saw tokens
     * for scrubbed values, so it passes e.g. search="[email_1]"; the tool must receive the real
     * address or its lookup finds nothing. Applied on every execution path (read, stream, confirm).
     *
     * @param array<array-key,mixed> $input
     * @return array<array-key,mixed>
     */
    public function rehydrateArguments(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $input[$key] = $this->rehydrateArguments($value);
            } elseif (is_string($value)) {
                $input[$key] = $this->vault->rehydrate($value);
            }
        }

        return $input;
    }

    /**
     * True when any argument still carries a vault token. Used on the write path: the vault is
     * request-scoped in V1, so a token in a confirmed write (a separate request, empty vault) would
     * otherwise be written verbatim as "[order_1]" into real data — and a prompt injection could try
     * to move a masked value into a write. A write is refused rather than run with a token in it.
     *
     * @param array<array-key,mixed> $input
     */
    public function containsToken(array $input): bool
    {
        foreach ($input as $value) {
            if (is_array($value) && $this->containsToken($value)) {
                return true;
            }
            if (is_string($value) && preg_match('/\[[a-z]+_\d+\]/', $value) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Swap vault tokens in the model's reply back to real values for the admin. Safe on any string,
     * a no-op when the reply carries no tokens.
     */
    public function rehydrate(string $text): string
    {
        return $this->vault->rehydrate($text);
    }
}
