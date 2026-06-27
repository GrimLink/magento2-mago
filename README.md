# Admin Assistant for Magento 2

AI-powered admin assistant — chat with your store using Claude or OpenAI.

## Features

- **Natural language chat** in the Magento admin panel
- **Real-time streaming** responses via SSE
- **Built-in skills** for sales analytics, store configuration, content management, and admin navigation
- **Extensible architecture** — third-party modules can register custom skills via DI
- **ACL-based permissions** — read/write access controlled per admin role
- **Write confirmation** — destructive actions always require explicit user approval
- **Multi-provider** — supports Claude (Anthropic) and OpenAI

## Requirements

- PHP >= 8.1
- Magento >= 2.4.4
- API key for Claude or OpenAI

## Installation

```bash
composer require maggy-assistant/magento2-base
bin/magento module:enable MaggyAssistant_Base
bin/magento setup:upgrade
```

## Configuration

`Stores > Configuration > Maggy Assistant > General`

1. **Enable** the module
2. **Select AI provider** (Claude or OpenAI)
3. **Enter API key**

### Internal API URL (Docker / reverse proxy setups)

If the module's internal REST API calls fail (e.g. in Docker environments where PHP can't reach itself via the public hostname), configure `Stores > Configuration > Maggy Assistant > API Settings > Internal API URL`.

Examples:
- markshust/docker-magento: `https://app:8443`
- DDEV: `https://ddev-<project>-web:443`
- Leave empty to use the store's base URL (works for most setups)

## Built-in Skills

| Skill area | Tools | Access |
|------------|-------|--------|
| Store Analytics | `sales_data`, `product_data`, `customer_data` | Read |
| Store Configuration | `config_reader`, `config_writer`, `cache_manager`, `indexer_manager` | Read / Write |
| Content Management | `cms_data`, `content_generator` | Read / Write |
| Navigation | `admin_navigator` | Read |

See [docs/skills-examples.md](docs/skills-examples.md) for example prompts per skill and [docs/skills-roadmap.md](docs/skills-roadmap.md) for the full roadmap of planned skills.

## Extending with Custom Skills

Third-party modules can register tools by implementing `ToolInterface` and adding them to the `ToolRegistry` via `di.xml`. No core modifications needed.

See [docs/skills-architecture.md](docs/skills-architecture.md) for the full architecture reference, including:

- How the skill/tool system works
- Step-by-step guide for building custom skills
- ACL and permissions model
- Data & privacy details
- MCP compatibility roadmap

## Permissions

| ACL Resource | Grants |
|---|---|
| `MaggyAssistant_Base::config` | Module configuration access |
| `MaggyAssistant_Base::assistant_read` | Read-only tools (analytics, config reading, navigation) |
| `MaggyAssistant_Base::assistant_write` | Write tools (config changes, CMS, content generation) |

## License

MIT
