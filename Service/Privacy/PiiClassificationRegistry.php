<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Privacy;

use MagoAssistant\Mago\Api\Skill\FieldClassifierInterface;

/**
 * Declares, per tool action, how each output field crosses to the LLM (issue #97).
 *
 * Completeness matters: an action not classified here (or via a FieldClassifierInterface) passes
 * through leniently, so a name or bare id it returns reaches the LLM. Any tool that can return
 * customer personal data must be classified.
 *
 * The legal research (issue #97 §8) is the authority here: the preference is NOT sending over
 * masking. Tokenised data with a server-side map is still pseudonymised personal data (Recital 26,
 * EDPB 01/2025), while absent data cannot leak. So direct identifiers (name, email, phone, address,
 * review nickname, review free text) are STRIP: never sent. Only a bare linkable id is TOKENISE, and
 * only so the assistant can still refer to a row across turns ([customer_N], [order_N]). The
 * admin_url is stripped because it embeds the admin secret key; the panel re-attaches a deep link
 * UI-side.
 *
 * Actions the map does not name (product_data, aggregates, cms_data, docs_search, config, cache,
 * indexer, navigation) carry no customer PII and pass through untouched. See PrivacyFilter's
 * $stripUnclassified flag for the eventual fail-closed-everything state (issue #97 decision 8, once
 * every tool declares its classification on the @api interface at 2.0.0).
 *
 * V1 scope: seeded from the actual output shapes of the four PII-carrying tools and the
 * linkable-ids-only tools in the #97 egress map. The production form moves this declaration onto
 * each action itself.
 */
class PiiClassificationRegistry
{
    private const TYPE_CUSTOMER = 'customer';
    private const TYPE_ORDER = 'order';
    private const TYPE_REVIEW = 'review';

    /** @var array<string,array<string,array{0:string,1?:string}>> keyed by action name */
    private const CLASSIFICATION = [
        // --- customer_data ---
        'lookup_customer' => [
            'entity_id' => [PiiClass::TOKENISE, self::TYPE_CUSTOMER],
            'name' => [PiiClass::STRIP],
            'email' => [PiiClass::STRIP],
            'telephone' => [PiiClass::STRIP],
            'country' => [PiiClass::PUBLIC],
            'city' => [PiiClass::PUBLIC],
            'registered' => [PiiClass::PUBLIC],
            'message' => [PiiClass::PUBLIC],
            'admin_url' => [PiiClass::STRIP],
        ],
        'recent_signups' => [
            'customer_id' => [PiiClass::TOKENISE, self::TYPE_CUSTOMER],
            'group_id' => [PiiClass::PUBLIC],
            'registered' => [PiiClass::PUBLIC],
            'store_id' => [PiiClass::PUBLIC],
            'period' => [PiiClass::PUBLIC],
            'total_new' => [PiiClass::PUBLIC],
            'admin_url' => [PiiClass::STRIP],
        ],
        'top_spenders' => [
            'customer_id' => [PiiClass::TOKENISE, self::TYPE_CUSTOMER],
            'total_spent' => [PiiClass::PUBLIC],
            'order_count' => [PiiClass::PUBLIC],
            'period' => [PiiClass::PUBLIC],
            'admin_url' => [PiiClass::STRIP],
        ],
        // --- sales_data ---
        'customer_orders' => [
            'customer_id' => [PiiClass::TOKENISE, self::TYPE_CUSTOMER],
            'period' => [PiiClass::PUBLIC],
            'total_orders' => [PiiClass::PUBLIC],
            'entity_id' => [PiiClass::TOKENISE, self::TYPE_ORDER],
            'order_number' => [PiiClass::TOKENISE, self::TYPE_ORDER],
            'total' => [PiiClass::PUBLIC],
            'status' => [PiiClass::PUBLIC],
            'items' => [PiiClass::PUBLIC],
            'date' => [PiiClass::PUBLIC],
            'admin_url' => [PiiClass::STRIP],
        ],
        'lookup_order' => [
            'entity_id' => [PiiClass::TOKENISE, self::TYPE_ORDER],
            'order_number' => [PiiClass::TOKENISE, self::TYPE_ORDER],
            'total' => [PiiClass::PUBLIC],
            'status' => [PiiClass::PUBLIC],
            'customer' => [PiiClass::STRIP],
            'email' => [PiiClass::STRIP],
            'date' => [PiiClass::PUBLIC],
            'admin_url' => [PiiClass::STRIP],
            // order item lines are product data, not customer PII:
            'sku' => [PiiClass::PUBLIC],
            'name' => [PiiClass::PUBLIC],
            'qty' => [PiiClass::PUBLIC],
            'price' => [PiiClass::PUBLIC],
            'row_total' => [PiiClass::PUBLIC],
        ],
        'search_orders' => [
            'query' => [PiiClass::PUBLIC],
            'period' => [PiiClass::PUBLIC],
            'results_count' => [PiiClass::PUBLIC],
            'entity_id' => [PiiClass::TOKENISE, self::TYPE_ORDER],
            'order_number' => [PiiClass::TOKENISE, self::TYPE_ORDER],
            'order_total' => [PiiClass::PUBLIC],
            'status' => [PiiClass::PUBLIC],
            'customer' => [PiiClass::STRIP],
            'date' => [PiiClass::PUBLIC],
            'product_sku' => [PiiClass::PUBLIC],
            'product_name' => [PiiClass::PUBLIC],
            'qty' => [PiiClass::PUBLIC],
            'line_total' => [PiiClass::PUBLIC],
        ],
        'recent_orders' => [
            'entity_id' => [PiiClass::TOKENISE, self::TYPE_ORDER],
            'order_number' => [PiiClass::TOKENISE, self::TYPE_ORDER],
            'total' => [PiiClass::PUBLIC],
            'status' => [PiiClass::PUBLIC],
            'items' => [PiiClass::PUBLIC],
            'store_id' => [PiiClass::PUBLIC],
            'date' => [PiiClass::PUBLIC],
            'admin_url' => [PiiClass::STRIP],
        ],
        // --- review_manager: free text is stripped, never masked (#97 §8) ---
        'list_pending' => self::REVIEW_LIST_CLASSES,
        'list_approved' => self::REVIEW_LIST_CLASSES,
    ];

    private const REVIEW_LIST_CLASSES = [
        'review_id' => [PiiClass::TOKENISE, self::TYPE_REVIEW],
        'title' => [PiiClass::STRIP],
        'nickname' => [PiiClass::STRIP],
        'detail' => [PiiClass::STRIP],
        'product_id' => [PiiClass::PUBLIC],
        'created_at' => [PiiClass::PUBLIC],
        'total' => [PiiClass::PUBLIC],
        'admin_url' => [PiiClass::STRIP],
    ];

    /** @var array<string,array<string,array{0:string,1?:string}>> action name => field map */
    private readonly array $classification;

    /**
     * The built-in map above covers the first-party PII tools. A third-party (or future) action can
     * declare its own classification by implementing FieldClassifierInterface and being registered in
     * the `classifiers` argument via di.xml: an opt-in extension point, no core edit and no @api
     * break. An injected classifier overrides a built-in of the same action name.
     *
     * @param FieldClassifierInterface[] $classifiers
     */
    public function __construct(array $classifiers = [])
    {
        $classification = self::CLASSIFICATION;
        foreach ($classifiers as $classifier) {
            $classification[$classifier->getClassifiedActionName()] = $classifier->getFieldClassification();
        }
        $this->classification = $classification;
    }

    /**
     * The field map for an action, or null when the action is not classified (a PII-free tool that
     * passes through, or one still to be declared).
     *
     * @return array<string,array{0:string,1?:string}>|null
     */
    public function classesFor(string $action): ?array
    {
        return $this->classification[$action] ?? null;
    }

    public function isClassified(string $action): bool
    {
        return isset($this->classification[$action]);
    }
}
