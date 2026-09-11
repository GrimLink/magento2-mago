# Privacy mode — V1 (issue #97)

Privacy mode keeps directly identifying customer data out of what the assistant sends to the LLM
provider. It is the only mode (no toggle), per the legal research in issue #97.

**Legal preference: not-sending over masking (#97 §8).** Tokenised data with a server-side map is
still pseudonymised personal data (Recital 26, EDPB 01/2025); absent data cannot leak. So direct
identifiers are **stripped** (never sent). Only a **bare linkable id** is **tokenised**, purely so the
assistant can refer to a row across turns (`[customer_N]`, `[order_N]`, `[review_N]`).

## The choke point

One place governs all tool output: `ChatService::executeTool()`'s return. V1 filters there, before
the result becomes the tool message sent to the model:

```php
$result = $tool->execute($input);
$result = $this->privacyService->filterToolResult(
    (string)($input['action'] ?? '') !== '' ? (string)$input['action'] : $toolCall['name'],
    $result
);
return $this->capToolResult($result, $toolCall['name']);
```

## Files (`Service/Privacy/`)

| File | Role |
|---|---|
| `PiiClass.php` | The three classes: `public` / `tokenise` / `strip`. |
| `PiiClassificationRegistry.php` | Per-action field → class map (strip-first), seeded from the real output shapes of the four PII-carrying tools + the linkable-ids-only tools. |
| `ConversationVault.php` | Reversible per-conversation token map; stable, typed tokens. |
| `PrivacyFilter.php` | Choke-point filter. Classified action: public passes, tokenise → token, everything else stripped. Unclassified action: pass-through in V1 (`$stripUnclassified=false`), or fail-closed strip when strict. |
| `PrivacyService.php` | Request-scoped facade ChatService calls: `filterToolResult()` + `rehydrate()`. |

## What crosses to the LLM

| Tool (action) | Stripped (never sent) | Tokenised (bare id) | Public |
|---|---|---|---|
| `lookup_customer` | name, email, telephone, admin_url | entity_id → `[customer_N]` | country, city, registered |
| `recent_signups` | admin_url | customer_id → `[customer_N]` | group_id, registered, store_id, period, total_new |
| `top_spenders` | admin_url | customer_id → `[customer_N]` | total_spent, order_count, period |
| `customer_orders` | admin_url | customer_id → `[customer_N]`, entity_id, order_number → `[order_N]` | period, total_orders, total, status, items, date |
| `lookup_order` | customer, email, admin_url | entity_id, order_number → `[order_N]` | total, status, date, item lines (sku/name/qty/price) |
| `search_orders` | customer | entity_id, order_number → `[order_N]` | query, period, results_count, order_total, status, date, product_sku/name/qty/line_total |
| `recent_orders` | admin_url | entity_id, order_number → `[order_N]` | total, status, items, store_id, date |
| `list_pending` / `list_approved` | title, nickname, detail (free text), admin_url | review_id → `[review_N]` | product_id, created_at, total |

Unclassified tools (aggregates, product_data, cms_data, docs_search, config, cache/indexer/nav) carry
no customer PII and pass through untouched.

Two rules apply to every result regardless of classification: `admin_url` is always stripped (it
embeds the admin secret key; the panel re-attaches a deep link UI-side), and the `error` / `message`
envelope is always kept (so a failed call or an ACL denial still reaches the model to be explained).

## Running the tests

```bash
# from the Magento root (module has no own phpunit binary)
vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
  vendor/mago-assistant/mago/Test/Unit/Service/Privacy/PrivacyFilterTest.php
# and the full module suite stays green with the ChatService wiring:
vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
  vendor/mago-assistant/mago/Test/Unit
```

## V1 scope vs the full #97 spec

Covered: the output-path choke point, strip-first classification for the PII/linkable tools, the
reversible vault, the ChatService wiring.

Deliberately deferred (documented, not bugs):
- **Rehydration for display** is not wired. Per #97 §8 the bare-id tokens "need no rehydration"; the
  admin reaches the record through the admin-panel deep link (to be re-attached UI-side).
- **Persistent per-conversation vault.** V1 vault is request-scoped — correct within one turn; cross-turn
  history replay needs persistence.
- **Strict fail-closed for every tool** waits on the classification moving onto the `@api` interface as
  a required method (#97 decision 8, the 2.0.0 break). Until then unclassified PII-free tools pass through.
- **Input-side scrubbing, tool-call arguments, page_form heuristic, verification harness (L0–L4)** —
  separate items in the #97 to-do.
