<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Privacy;

/**
 * The privacy-mode entry point ChatService talks to (issue #97). One request-scoped instance holds
 * the conversation vault, so a value tokenised while filtering a tool result rehydrates to the same
 * value when the model's reply is shown to the admin. Magento shares one instance of a non-virtual
 * type per request by default, which is what makes that hold.
 *
 * V1 scope: the vault is request-scoped. Cross-turn history replay needs a vault persisted per
 * conversation (#97 mechanism) — a documented follow-up, not a correctness bug within one turn.
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
     * Swap vault tokens in the model's reply back to real values for the admin. Safe on any string,
     * a no-op when the reply carries no tokens.
     */
    public function rehydrate(string $text): string
    {
        return $this->vault->rehydrate($text);
    }
}
