# Plugins

Plugins extend Birkly with extra features: new admin pages, custom APIs, additional field types, or integrations. You install and enable them from the admin; they can add menu items, hooks into content or automation, and more.

## Summary

**Plugins** add functionality to Birkly without changing the core code. In the admin, go to **Settings** (or **Plugins**) to see available plugins. Install or enable the ones you need; some may require configuration (API keys, options). Once active, they may add new items in the sidebar, new fields in collections, or new automation actions. Disable or uninstall from the same place if you no longer need them. Only install plugins from trusted sources; they can access content and settings.

## Beginner

**Plugins** are add-ons that give Birkly extra features. Think of them like apps on a phone: you choose which ones to turn on, and they add new screens, options, or behavior without you editing the core program.

**What plugins can do (examples)**

- **New admin pages** — e.g. “Analytics” or “User management” in the sidebar.
- **New field types** — When editing a collection, you might see extra field types (e.g. Map, Rating) that come from a plugin.
- **Automation** — New triggers or actions (e.g. “Send to Mailchimp”, “Create Trello card”).
- **Integrations** — Connect Birkly to other services (email, CRM, Slack) via the plugin’s settings.

**How to use plugins**

1. In the admin, open **Settings** (or **Plugins** in the sidebar).
2. You’ll see a list of **available** plugins (those that are in your Birkly `plugins/` folder and that Birkly has detected).
3. **Enable** a plugin by turning it on or clicking “Enable”. Some plugins need to be installed first (your host or developer may add the plugin folder to the server).
4. After enabling, some plugins show a **settings** or **configuration** page where you enter API keys, URLs, or options. Fill those in so the plugin can work.
5. To **disable** a plugin, turn it off in the same list. To **remove** it completely, disable it and then (if you have access to the server) delete the plugin’s folder from `plugins/`. Check the plugin’s readme for uninstall steps.

**Example: enabling a “Slack” plugin**

1. Go to **Settings → Plugins**.
2. Find “Slack integration” (or similar) and click **Enable**.
3. Open the plugin’s settings and enter your Slack webhook URL (or connect a Slack app).
4. Save. You may now see a “Slack” action in Automation workflows or a new menu item.

**Safety**

Plugins run with the same access as the CMS. They can read and change content and settings. Only install plugins from sources you trust, and keep them updated. If you no longer need one, disable it.

**Official plugins (marketplace)**

CMS downloads do **not** include plugins. Install official Birkly extensions from **Plugins → Marketplace** (free, one-click) or [marketplace.birkly.io](https://marketplace.birkly.io).

| Plugin | Marketplace | Docs |
|--------|-------------|------|
| **Commerce** | [birkly-commerce](https://marketplace.birkly.io/offers/birkly-commerce) | [Commerce plugin](plugins/commerce.md) |
| **Events** | [birkly-events](https://marketplace.birkly.io/offers/birkly-events) | [Events functions](../03-templating/events-functions.md) |
| **Automation** | [birkly-automation](https://marketplace.birkly.io/offers/birkly-automation) | [Automation overview](../05-automation/automation-overview.md) |
| **User Management** | [birkly-user-management](https://marketplace.birkly.io/offers/birkly-user-management) | [User plugin](user-plugin.md) |

See [Marketplace go-live — installing plugins](marketplace-go-live.md) for fresh-install workflow and grandfathered upgrades.

**Birkly Marketplace**

Browse official and community extensions at [marketplace.birkly.cloud](https://marketplace.birkly.cloud).

| Role | Guide |
|------|-------|
| **Plugin economy curriculum** | [Overview](../06-plugin-economy/overview.md) — full multi-page guide |
| **Buyer** (install in CMS) | [For site owners](../06-plugin-economy/for-site-owners.md) · [Marketplace — buyer license and install](marketplace-buyer-guide.md) |
| **Provider** (publish offers) | [For providers](../06-plugin-economy/for-providers.md) · [Marketplace — provider publishing guide](marketplace-provider-guide.md) |
| **Go-live / unbundling** | [Marketplace go-live — installing plugins](marketplace-go-live.md) |

## Advanced Users

**Capabilities**

- **Admin UI** — New pages, sidebar items, panels. Registered via plugin.json (navigation) and plugin.php (routes).
- **API** — New endpoints (e.g. `/api/plugin_name/...`) for custom integrations.
- **Field types** — Register new field types in the collection editor; see core/plugins and FieldRegistry.
- **Hooks / events** — Run code on content save, user login, automation run, etc. Event names and payloads are defined in core/plugins/events.php or equivalent.
- **Templating** — Expose template functions or variables.
- **Automation** — Register triggers or actions. Exact API depends on core/automation and plugin_integration.

**Structure (developer)**

| Path | Purpose |
|------|---------|
| `plugin.json` | Metadata: name, version, description; optional navigation (sidebar label, icon, page). Icon: registry name or inline SVG — see [Plugin navigation icon](../06-plugin-economy/plugin-navigation-icon.md). |
| `plugin.php` | Main entry; registers hooks, routes, services. |
| `admin/` | HTML/JS for admin pages. |
| `api/` | Optional PHP endpoints under `/api/plugin_handle/...`. |

The plugin system may support capabilities, permissions, migrations, scheduled tasks, and template functions. See core/plugins/ and developer docs for the full API.

**Installing**

- Plugins live in `plugins/{plugin-folder}/`. Add the folder (via Git, FTP, or host file manager); Birkly detects it and lists it under Settings → Plugins. Enable in the admin; some require config (API keys, options).

**Security and updates**

- Plugins run with CMS privileges; a malicious or buggy plugin can access or modify content and settings. Install only from trusted sources; keep plugins updated. See [Updates](updates.md) for update process.

**Disabling and removing**

- **Disable** in the admin to turn off without deleting files. **Uninstall** (if the plugin supports it) may run cleanup or remove its data. To fully remove: disable, then delete the plugin folder from `plugins/`. Check the plugin readme for uninstall hooks or data to clear.

**Troubleshooting activation**

- If **User Management** (or another marketplace-installed plugin) fails with a generic “dependencies not met” message on an up-to-date install, check the detailed `errors[]` in the plugin settings response. On older cores, a registry bug (`$haru_plugins` vs `$birkly_plugins`) blocked activation — update Birkly first. See [Troubleshooting](../07-operations/troubleshooting.md#user-management-plugin-wont-enable).

## Related

- [Plugin economy overview](../06-plugin-economy/overview.md)
- [Official plugins](../06-plugin-economy/official-plugins.md)
- [Security and review](../06-plugin-economy/security-and-review.md)
- [Marketplace go-live — installing plugins](marketplace-go-live.md)
- [Marketplace — buyer license and install](marketplace-buyer-guide.md)
- [Marketplace — provider publishing guide](marketplace-provider-guide.md)
- [Settings](../01-admin/settings.md)
- [Commerce plugin](plugins/commerce.md)
- [Updates](updates.md)
- [Automation overview](../05-automation/automation-overview.md)
