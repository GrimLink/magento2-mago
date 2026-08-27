# Admin Assistant for Magento 2

AI-powered admin assistant — chat with your store using the AI provider of your choice.

## Features

- **Natural language chat** in the Magento admin panel
- **Real-time streaming** responses via SSE
- **Built-in skills** for sales analytics, store configuration, content management, and admin navigation
- **Extensible architecture** — third-party modules can register custom skills via DI
- **ACL-based permissions** — read/write access controlled per admin role
- **Write confirmation** — destructive actions always require explicit user approval
- **Multi-provider** — Anthropic, OpenAI, Azure, Google Gemini, DeepSeek, Hugging Face, OpenRouter, Ollama and LM Studio, through [MageOS_AiBase](https://github.com/mage-os-lab/module-ai-base)
- **Documentation grounding** — answers admin how-to questions from Magento/Adobe Commerce docs, fetched into your database (optional)

## Requirements

- PHP >= 8.2
- Magento >= 2.4.9 or Mage-OS >= 3.0 (Symfony 7.3+ required by the AI bridges)
- An API key for one of the supported providers

## Installation

Anthropic and OpenAI are included out of the box. For other providers (Azure, Gemini, DeepSeek,
Ollama, LM Studio, etc.) install the matching Symfony AI bridge — see `composer.json` suggests.

```bash
composer require maggy-assistant/magento2-base
bin/magento module:enable MageOS_AiBase MaggyAssistant_Base
bin/magento setup:upgrade
```

## Configuration

First add a provider under `Stores > Configuration > Mage-OS > AI Configuration`: pick the
backend, paste the API key, choose a model, and use **Test Connection** to check it answers.

Then, under `Stores > Configuration > Maggy Assistant`:

1. **Enable** the module (General)
2. **Pick the AI Service** the assistant runs on (API Settings). Leave it on *Automatic* to use
   the first usable one, which is what a single-provider store wants.

### Internal API URL (Docker / reverse proxy setups)

If the module's internal REST API calls fail (e.g. in Docker environments where PHP can't reach itself via the public hostname), configure `Stores > Configuration > Maggy Assistant > API Settings > Internal API URL`.

Examples:
- markshust/docker-magento: `https://app:8443`
- DDEV: `https://ddev-<project>-web:443`
- Leave empty to use the store's base URL (works for most setups)

These calls verify the TLS certificate by default. If the internal URL points at a host whose certificate cannot match (loopback addresses, container hostnames, self-signed certificates), set `Verify TLS Certificate` to No. Keep it enabled in production.

## Built-in Skills

| Skill area | Tools | Access |
|------------|-------|--------|
| Store Analytics | `sales_data`, `product_data`, `customer_data` | Read |
| Store Configuration | `config_reader`, `config_writer`, `cache_manager`, `indexer_manager` | Read / Write |
| Content Management | `cms_data`, `content_generator` | Read / Write |
| Navigation | `admin_navigator` | Read |
| Documentation | `docs_search` | Read |

See [docs/skills-examples.md](docs/skills-examples.md) for example prompts per skill and [docs/skills-roadmap.md](docs/skills-roadmap.md) for the full roadmap of planned skills.

## Documentation grounding

When enabled, the assistant can answer "how do I…" questions from the official Magento admin documentation instead of guessing. The `docs_search` skill runs a MySQL FULLTEXT search over an indexed copy of the docs and cites the source page it used.

### Configuration

`Stores > Configuration > Maggy Assistant > Documentation`

| Field | Config path | Default | Purpose |
|---|---|---|---|
| Enable documentation grounding | `maggy/docs/enabled` | `0` | Master switch; also gates the sync cron |
| Source repository | `maggy/docs/source_repo` | `mage-os/mirror-commerce-admin.en` | GitHub `owner/repo` to index. Default is the MIT-licensed Mage-OS mirror of Adobe's Commerce Admin docs |
| Source branch / commit | `maggy/docs/ref` | `main` | Branch or commit to index; pin to a commit for reproducibility |
| Results per search | `maggy/docs/top_k` | `5` | Max doc pages returned per search |
| Sync schedule | `maggy/docs/cron_expr` | `0 4 1 * *` (monthly) | Cron expression for the re-index job |

### How the corpus is built

A cron job (`maggy_docs` group, its own process) indexes the docs into the `maggy_doc` table (~5 MB). To index immediately instead of waiting for the cron:

```bash
bin/magento maggy:docs:index --force
```

Sync is cheap to run often because it is **change-detected by git tree SHA**: an unchanged source repo costs a single API call and skips re-fetching entirely. This is why the default schedule can safely be raised when you feed docs that update more often than the Mage-OS mirror. On a real change, every `help/**.md` is fetched (including `_includes/`, needed to resolve `{{$include}}` partials), Experience League markup is normalized to plain text, and the table is swapped in a single transaction — a failed sync keeps the previous corpus.

### Why MySQL FULLTEXT (not embeddings)

Retrieval uses a MySQL FULLTEXT index rather than vector embeddings so the feature works on any Magento install with **zero extra infrastructure** — no vector database, no embeddings service, and no dependency on a specific AI provider (Anthropic, for one, has no embeddings API). For a bounded, well-structured doc corpus this keyword search is accurate enough, and the assistant compensates for the lack of semantic matching by issuing multiple searches with different terms when the first result set is thin. Semantic/vector retrieval can be added later as an optional backend without changing the skill contract.

### Custom docs

The pipeline is source-repo agnostic: point `Source repository` at any public GitHub repo of Adobe Experience League-flavored (or plain) markdown to ground the assistant on your own documentation. Private repos and non-GitHub sources are on the roadmap.

## Extending with Custom Skills

Third-party modules can register tools by implementing `ToolInterface` and adding them to the `ToolRegistry` via `di.xml`. No core modifications needed.

See [docs/skills-architecture.md](docs/skills-architecture.md) for the full architecture reference, including:

- How the skill/tool system works
- Step-by-step guide for building custom skills
- ACL and permissions model
- Data & privacy details
- MCP compatibility roadmap

## Testing

End-to-end tests run with Playwright against Chromium. No test calls a real provider: the
chat panel is covered with browser-level SSE stubs, the backend with WireMock standing in for
the provider endpoint.

```bash
cd Test/End-2-end
npm install && npx playwright install chromium
BASE_URL="https://your-store.test/" npx playwright test
```

See [Test/End-2-end/README.md](Test/End-2-end/README.md) for configuration, the WireMock setup
and how to add scenarios.

## Permissions

| ACL Resource | Grants |
|---|---|
| `MaggyAssistant_Base::config` | Module configuration access |
| `MaggyAssistant_Base::assistant_read` | Read-only tools (analytics, config reading, navigation) |
| `MaggyAssistant_Base::assistant_write` | Write tools (config changes, CMS, content generation) |

## License

MIT
