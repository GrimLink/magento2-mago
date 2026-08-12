# End-2-end tests

Playwright tests for the admin chat panel (`view/adminhtml/web/js/chat-panel.js`).
Layout and configuration follow [mollie/magento2](https://github.com/mollie/magento2/tree/master/Test/End-2-end).

## How the AI is kept out of the loop

The browser never talks to Claude or OpenAI. It reads an SSE stream of
`text` / `tool_call` / `confirm` / `done` / `error` events from `maggy/chat/stream`.
`ChatMock` intercepts that endpoint and replays the fixtures in `fixtures/scenarios.ts`,
so every run is deterministic, free and offline. No API key is needed and no test ever
calls a model.

## Configuration

Everything is read from environment variables, no config file to edit:

| Variable | Default | Meaning |
|---|---|---|
| `BASE_URL` | `https://mago.test/` | Magento base URL, with trailing slash |
| `ADMIN_PATH` | `admin` | `backend/frontName` from `app/etc/env.php` |
| `ADMIN_USERNAME` | `exampleuser` | Admin username |
| `ADMIN_PASSWORD` | `examplepassword123` | Admin password |

```bash
BASE_URL="https://your-store.test/" npx playwright test
```

To keep values in a file instead, uncomment the dotenv lines at the top of
`playwright.config.ts` and add a `.env`.

## Prerequisites

The module has to be switched on, otherwise the panel is not rendered at all:

```bash
bin/magento config:set maggy/general/enabled 1
bin/magento cache:flush
```

An AI service has to be configured too, or the panel answers every question with an error. The
backend tests set one up below; for the browser-level tests any row will do, since they never
reach a provider.

On a freshly installed store, Magento's admin usage tracking modal covers the screen on first
login and swallows every click. Either answer it once by hand or disable the module:

```bash
bin/magento module:disable Magento_AdminAnalytics
```

The `setup` project logs in once and stores the session in `.auth/backend.json`,
which every other test reuses. It fails with an explicit message if the panel is missing.

## Running

```bash
npm install
npx playwright install chromium

BASE_URL="https://your-store.test/" npx playwright test
npx playwright test --headed    # watch it happen
npx playwright test --ui        # Playwright UI mode
```

Reports: `npx playwright show-report`.

The backend tests need an AI service pointed at WireMock instead of Anthropic. Providers live in
`MageOS_AiBase`, so this is one service row rather than a handful of `maggy/api/*` values:

```bash
docker run -d --name wiremock -p 8080:8080 -v "$(pwd)/wiremock:/home/wiremock:ro" wiremock/wiremock:3.13.1
bin/magento config:set mageos_ai/services/configuration \
  '{"_e2e_row_1":{"anthropic":{"api_key":"not-a-real-key","model":"claude-sonnet-4-6","base_url":"http://wiremock:8080"}}}'
bin/magento cache:flush config
```

**This overwrites every AI service already configured on the install, and stored API keys cannot
be read back once gone.** Point it at a throwaway store, or save the row first:

```bash
bin/magento config:show mageos_ai/services/configuration
```

The base URL stops at the host: the Anthropic bridge appends `/v1/messages` itself. The hostname
has to resolve from inside the container running Magento, so put WireMock on the same network. See
`.github/workflows/templates/docker-compose.yml` for a working example.

`config:set` does not run the field's backend model, so the row lands unencrypted. That is fine for
a throwaway key WireMock never checks, and the selector reads plaintext rows; anything real should
be entered through the admin form, which encrypts it.

The bridge validates the model against its own catalogue before any request leaves, so a model it
does not know fails with `No provider found for model` and never reaches WireMock. Keep the model
in the row to one `symfony/ai-anthropic-platform` ships.

## Browsers

Chromium only (`devices['Desktop Chrome']`). Firefox, WebKit and the mobile
viewports are present but commented out in `playwright.config.ts`, same as Mollie.

## Layout

```
global-setup.ts                     runs once, before the setup project
tsconfig.json                       Pages/ Actions/ Fixtures/ path aliases
fixtures/scenarios.ts               canned SSE conversations, one per scenario
support/pages/backend/BackendLogin  admin login
support/pages/backend/ChatPanel     locators and interactions for the chat panel
support/actions/backend/ChatMock    SSE builder and route interception
tests/auth/backend-auth.setup.ts    writes .auth/backend.json
tests/*.spec.ts                     the tests
```

## Adding a scenario

Add an entry to `fixtures/scenarios.ts` describing the events the backend would emit,
then drive it with `chatMock.install(page, scenario)`. A write action needs a `confirm`
event plus a `status` payload, because the frontend resolves the message id through
`maggy/chat/status` when it only has a conversation id.

## Known gaps

These tests stop at the SSE boundary. Nothing here covers `ChatService`, tool
execution, ACL checks or database writes, and nothing detects the model no longer
picking the right tool after a prompt or schema change.

There is no product-creation tool. `product_data` exposes `search`, `get_by_sku`,
`count` and `low_stock`, all read-only, so "create a product" cannot succeed.
`catalog-questions.spec.ts` pins the assistant to declining rather than reporting a
success that never happened. Delete that test once a write tool lands.

`route.fulfill` delivers the whole response body at once, so these tests do not
exercise chunk-boundary buffering in the SSE reader. Covering that needs a server
that streams in deliberately awkward slices.
