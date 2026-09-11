<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Privacy;

/**
 * The single choke point (issue #97): a tool result is filtered here before it becomes the tool
 * message sent to the LLM (ChatService::executeTool()'s return, via capToolResult()).
 *
 * For a classified action: public fields pass, tokenise fields become stable vault tokens, and
 * every other scalar field — a strip-classified identifier, or one the action never declared — is
 * dropped (the legal preference is not-sending over masking, #97 §8).
 *
 * For an UNclassified action the behaviour depends on $stripUnclassified:
 *  - false (V1 default): pass through untouched. The unclassified tools are the PII-free ones
 *    (aggregates, catalog, config, docs), which the egress map allows freely.
 *  - true: strip every scalar (the eventual fail-closed-everything state once every tool declares
 *    its classification on the @api interface — #97 decision 8, at 2.0.0).
 *
 * Structure is preserved: nested arrays are always walked, so "results"/"recent"/"order" wrappers
 * and their records keep their shape; classification applies to the scalar leaves inside them.
 */
class PrivacyFilter
{
    public function __construct(
        private readonly PiiClassificationRegistry $registry,
        private readonly ConversationVault $vault,
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
        if ($classes === null && !$this->stripUnclassified) {
            return $result;
        }

        return $this->apply($result, $classes);
    }

    /**
     * @param array<array-key,mixed> $node
     * @param array<string,array{0:string,1?:string}>|null $classes
     * @return array<array-key,mixed>
     */
    private function apply(array $node, ?array $classes): array
    {
        $out = [];
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $out[$key] = $this->apply($value, $classes);
                continue;
            }

            $rule = is_string($key) && $classes !== null ? ($classes[$key] ?? null) : null;
            $class = $rule[0] ?? PiiClass::STRIP;

            if ($class === PiiClass::PUBLIC) {
                $out[$key] = $value;
            } elseif ($class === PiiClass::TOKENISE) {
                $out[$key] = $this->vault->tokenise((string)$value, $rule[1] ?? 'value');
            }
            // STRIP (declared, or the fail-closed default for an undeclared field): drop it.
        }

        return $out;
    }
}
