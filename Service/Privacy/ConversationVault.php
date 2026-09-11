<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Privacy;

/**
 * The reversible token map for one conversation (issue #97). A PII value is replaced with a stable,
 * type-carrying token on the way to the LLM and swapped back on the way to the admin. The same value
 * always yields the same token within the conversation, so a token emitted on one turn still resolves
 * when the conversation history is replayed on the next.
 *
 * POC scope: request-scoped in memory. Production must persist this per conversation (each turn is a
 * fresh PHP process) and use a salted, real-data-improbable token grammar instead of [type_n].
 */
class ConversationVault
{
    /** @var array<string,string> "type\0value" => token */
    private array $tokenByValue = [];

    /** @var array<string,string> token => original value */
    private array $valueByToken = [];

    /** @var array<string,int> type => highest issued number */
    private array $counters = [];

    /**
     * Returns the stable token for a value, minting one on first sight. An empty value is left as-is:
     * there is nothing identifying to hide, and a token for "" would collide across every blank field.
     */
    public function tokenise(string $value, string $type): string
    {
        if ($value === '') {
            return $value;
        }

        $key = $type . "\0" . $value;
        if (isset($this->tokenByValue[$key])) {
            return $this->tokenByValue[$key];
        }

        $number = $this->counters[$type] = ($this->counters[$type] ?? 0) + 1;
        $token = sprintf('[%s_%d]', $type, $number);

        $this->tokenByValue[$key] = $token;
        $this->valueByToken[$token] = $value;

        return $token;
    }

    /**
     * Swaps every known token found anywhere in the text back to its original value. An unknown token
     * (hallucinated, or from a cleaned conversation) is deliberately left untouched here; the display
     * layer decides how to present it, and a write path must refuse it.
     */
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
}
