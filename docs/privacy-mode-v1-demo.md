# Privacy mode V1 proof

Real output of the V1 filter (`Service/Privacy/PrivacyFilter`) on the two heaviest tools. This is
what actually happens at the `ChatService::executeTool()` choke point before anything reaches the
LLM provider. Reference: `docs/privacy-mode.md`. Legal basis: issue #97 (not-sending over masking).

## `lookup_customer`

**Raw result (what the admin sees in the shop):**
```json
{
    "entity_id": 42,
    "name": "Jan Jansen",
    "email": "jan@example.com",
    "country": "NL",
    "city": "Amsterdam",
    "telephone": "0612345678",
    "registered": "2026-01-05 10:00:00",
    "admin_url": "https://shop.test/admin/customer/index/edit/id/42/key/abc123secret/"
}
```

**What the LLM provider receives:**
```json
{
    "entity_id": "[customer_1]",
    "country": "NL",
    "city": "Amsterdam",
    "registered": "2026-01-05 10:00:00"
}
```

Name, email, telephone: **stripped** (never sent). `admin_url`: **stripped** (it embedded the admin
secret key `abc123secret`). `entity_id`: **tokenised** to `[customer_1]` so the assistant can still
refer to the customer across turns. `city`/`country`/`registered` stay, so "which customers are in
Amsterdam" still works.

## `lookup_order`

**Raw result:**
```json
{
    "entity_id": 7, "order_number": "000000549", "total": 149.95, "status": "processing",
    "customer": "Jan Jansen", "email": "jan@example.com",
    "items": [{"sku": "ABC-1", "name": "Helmet", "qty": 1, "price": 149.95}],
    "date": "2026-01-06",
    "admin_url": "https://shop.test/admin/sales/order/view/order_id/7/key/deadbeef/"
}
```

**What the LLM provider receives:**
```json
{
    "entity_id": "[order_1]", "order_number": "[order_2]", "total": 149.95, "status": "processing",
    "items": [{"sku": "ABC-1", "name": "Helmet", "qty": 1, "price": 149.95}],
    "date": "2026-01-06"
}
```

Customer name and email: **stripped**. Order ids: **tokenised**. Product line items (catalog data,
not customer PII) and order totals/status/date: **kept**, so the assistant stays useful.

## Verified by

`Test/Unit/Service/Privacy/PrivacyFilterTest.php` (9 tests) and the full module unit suite
(155 tests) stay green with the ChatService wiring in place.
