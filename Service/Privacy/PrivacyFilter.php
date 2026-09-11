<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Privacy;

/**
 * The single choke point (issue #97): a tool result is filtered here before it becomes the tool
 * message sent to the LLM (ChatService::executeTool()'s return). For a classified action, public
 * fields pass, tokenise fields become stable vault tokens, and every other scalar (strip-classified,
 * or undeclared) is dropped; the legal preference is not-sending over masking (#97 section 8). An
 * unclassified action passes through when $stripUnclassified is false (V1: the unclassified tools are
 * the PII-free ones), or is stripped scalar-by-scalar when true (the future fail-closed default).
 * Nested arrays are always walked, so wrappers and records keep their shape.
 */
class PrivacyFilter
{
    /**
     * Dropped from every result whatever its classification: admin_url embeds the admin secret key
     * (/key/<hash>/) and must never reach the LLM. (AdminNavigator's own "url" field is the tool's
     * deliverable, a separate concern; see the admin_url sibling issue.)
     */
    private const ALWAYS_STRIP = ['admin_url'];

    /**
     * Kept whatever the classification so a tool's failure or ACL denial can still be explained,
     * otherwise a classified action returning only an error would reach the model as {}. The value is
     * still run through the heuristic, since a missed lookup echoes the rehydrated search term back.
     */
    private const ALWAYS_ALLOW = ['error', 'message'];

    public function __construct(
        private readonly PiiClassificationRegistry $registry,
        private readonly ConversationVault $vault,
        private readonly PiiHeuristic $heuristic,
        private readonly bool $stripUnclassified = false
    ) {
    }

    /**
     * @param array<string,mixed> $result
     * @return array<string,mixed>
     */
    public function filter(string $action, array $result): array
    {
        $classes = $this->registry->classesFor($action);
        $lenient = $classes === null && !$this->stripUnclassified;

        return $this->apply($result, $classes, $lenient);
    }

    /**
     * @param array<array-key,mixed> $node
     * @param array<string,array{0:string,1?:string}>|null $classes
     * @return array<array-key,mixed>
     */
    private function apply(array $node, ?array $classes, bool $lenient): array
    {
        $out = [];
        foreach ($node as $key => $value) {
            $keyStr = is_string($key) ? $key : null;

            if ($keyStr !== null && in_array($keyStr, self::ALWAYS_STRIP, true)) {
                continue;
            }

            $rule = $keyStr !== null && $classes !== null ? ($classes[$keyStr] ?? null) : null;

            // An explicit STRIP rule wins over the structure: a field declared STRIP is dropped
            // whether it arrives as a scalar or as a nested array (so a customer object under a
            // STRIP key cannot leak its leaves through the recursion below).
            if ($rule !== null && $rule[0] === PiiClass::STRIP) {
                continue;
            }

            if (is_array($value)) {
                $out[$key] = $this->apply($value, $classes, $lenient);
                continue;
            }

            if ($keyStr !== null && in_array($keyStr, self::ALWAYS_ALLOW, true)) {
                $out[$key] = $this->keep($value);
                continue;
            }
            if ($lenient) {
                $out[$key] = $this->keep($value);
                continue;
            }

            $class = $rule[0] ?? PiiClass::STRIP;

            if ($class === PiiClass::PUBLIC) {
                $out[$key] = $this->keep($value);
            } elseif ($class === PiiClass::TOKENISE) {
                $out[$key] = $this->vault->tokenise((string)$value, $rule[1] ?? 'value');
            }
            // STRIP (declared, or the fail-closed default for an undeclared field): drop it.
        }

        return $out;
    }

    /**
     * A kept value (public, envelope or lenient pass-through) still goes through the PII heuristic:
     * a rehydrated argument the tool echoes back (a lookup miss repeating the search email in its
     * message, or search_orders echoing the query) would otherwise cross to the LLM raw. The vault
     * returns the same token, so the model's continuity is unaffected; a non-string is left as is.
     */
    private function keep(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        // Defang any token-lookalike arriving in tool output BEFORE minting real tokens, so a forged
        // "[email_1]" planted in an unclassified tool's data (a poisoned product name, CMS text)
        // cannot reach the model and be echoed into an argument that then rehydrates to a real value.
        $value = (string)preg_replace('/\[([a-z]+_\d+)\]/', '($1)', $value);

        return $this->heuristic->tokeniseFreeText($value, $this->vault);
    }
}
