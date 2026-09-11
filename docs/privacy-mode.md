# Privacy mode V1 (issue #97)

Privacy mode keeps directly identifying customer data out of what the assistant sends to the LLM
provider. It is the only mode (no toggle), per the legal research in issue #97.

> ## Any tool that returns customer data MUST be classified
>
> Privacy relies on completeness. In V1 an **unclassified** tool passes through leniently. The
> heuristic still catches email / phone / IBAN / BSN / VAT in its output, but a customer **name** or
> a bare **linkable id** it returns will reach the LLM raw. If you add a skill/action that can return
> personal data, you MUST classify it, either in `PiiClassificationRegistry` (first-party) or by
> implementing `Api\Skill\FieldClassifierInterface` and registering it in di.xml (third-party). The
> hard fail-closed-everything default (strip unless declared) is gated behind a future major.

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
| `PiiHeuristic.php` | Detects email / IBAN / BSN / VAT / Dutch phone in free text (checksums), for the paths with no declared field to classify. |
| `PrivacyService.php` | Request-scoped facade ChatService uses: filter results, scrub messages, rehydrate arguments and the reply, begin the conversation vault. |

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

## Covered

- Output-path choke point with strip-first classification for the PII/linkable tools.
- Input-side scrubbing of typed PII, replayed history and the custom system prompt (`PiiHeuristic`:
  email, IBAN, BSN, VAT, Dutch phone).
- Tool-call arguments rehydrated before a read runs; a write that still carries a token is refused.
- Persistent per-conversation vault (`mago_pii_token`, with graceful fallback to request scope until
  `setup:upgrade` creates the table).
- Display rehydration for the admin: the streamed reply and the history view show real values while
  the stored copy stays tokenised.
- Opt-in `FieldClassifierInterface` so a third-party tool can classify itself.
- AI Act "AI assistant" label in the chat header.

## Deferred (see `privacy-mode-v2.md` and the sibling tickets)

- **Strict fail-closed for every tool** (strip unless declared) waits on classification becoming a
  required `@api` method (V2, a major release).
- page_form field classification (once PR #67 lands in main).
- The three sibling security issues: ConfigReader allowlist (#106), admin_url secret key (#107),
  retention and right-to-erasure (#108).
