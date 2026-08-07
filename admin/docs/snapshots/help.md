# Help and Onboarding

## Summary

**Help** is the in-admin documentation: a link or icon in the sidebar opens guides that explain collections, entries, field types, templating, and integration. The CMS fetches a **live manifest** from Birkly-Docs when online, with **offline fallback** to bundled snapshots. **First-run** starts with **START_HERE.html** in the release zip (extract, open in a browser, pick local or cloud install path) before you log in to the admin. **Onboarding** is the setup wizard shown when the first admin logs in — wizard v2 includes install context, use case, a marketplace hint, and an optional BYOAI mini-wizard. The Dashboard **setup checklist v2** shows essential items only; an AI reminder appears **only** if you skipped the wizard AI step. If the instance was preconfigured, onboarding may not appear.

## Beginner

**Help**

The **Help** section (often a "?" icon or a "Help" link in the sidebar) opens documentation **inside** the admin. You don't have to leave the panel or search the web—you can read how to create a collection, what field types mean, how to connect your website, and so on.

- **What's in Help:** Topics match Birkly-Docs: Getting started, Content, AI (connections, MCP, approvals), Automation, Commerce, Marketplace, User plugin, ContentEditor blocks, Plugins, Media, Settings, and more. Each topic has a short in-admin snapshot and a **Read full doc** link to the published HTML guide on [docs.birkly.com](https://docs.birkly.com).
- **Live docs:** When your server can reach Birkly-Docs, Help loads the latest topic list from the public manifest. **Read full doc** opens the matching page in a new tab.
- **Offline:** If the manifest cannot be fetched, Help falls back to **bundled snapshots** under `admin/docs/snapshots/` so basic guidance still works air-gapped.

**Example:** You're not sure how to add a "Featured image" to a blog post. You click **Help**, open the "Entries" or "Field types" topic, and follow the steps. You never leave the admin.

**First-run (START_HERE.html)**

Before your first admin login, open **START_HERE.html** from the extracted release zip (it sits next to `admin/`, `api/`, and `core/` — not inside the zip file).

1. **Extract the zip** to a folder on your computer or upload it to your web host.
2. **Open START_HERE.html** in a browser. A folder check confirms you're in the right place (if you see a nested-folder warning, move up one level and try again).
3. **Choose your install path:** **On your computer** (local PHP server) or **On a web server** (FTP/cloud upload). Follow the steps shown for your path.
4. Click **Start setup** — this opens the admin onboarding wizard with your install path remembered (`local` or `cloud`).

**Example:** You download Birkly, extract to `Documents/Birkly`, double-click `START_HERE.html`, pick **On your computer**, run `./start-server.sh`, then click **Start setup**. The wizard opens with local-specific hints.

**Onboarding (wizard v2)**

**Onboarding** is a setup wizard that appears when you reach the admin for the first time (or when no admin account exists yet). A progress bar at the top shows which step you're on. If you started from START_HERE, your **install path** (local or cloud) is already set and the wizard tailors copy accordingly.

**Wizard steps**

0. **Create admin account** *(first user only)* — Set email and password when no users exist yet.
1. **Welcome** — Brief intro; you can skip the entire wizard from here.
2. **Install context** — Confirms how you're running Birkly (local vs cloud) based on START_HERE or the `?install=local|cloud` link.
3. **Site info** — Set your **project name** (appears in the admin and page titles) and optionally a short description or logo.
4. **Use case + demo content** — Pick what you're building (blog, business site, shop, docs, developer project). Optionally keep or remove starter demo content. Skippable.
5. **Hub** — A hint that official plugins install from **Plugins → Hub** (Commerce, Events, Automation, and more). Skippable.
6. **Connect AI** *(optional)* — A **BYOAI mini-wizard**: pick provider → enter API key → test → set role and approval defaults. **Skip for now** and **Set up later** are always visible; AI is never required. Skippable.
7. **Complete** — Summary and **Go to Dashboard**.

- **Skipping any step:** Each step has **Skip** or **Skip for now**. Skipping moves you to the next step without making changes.
- **Resume:** If you leave partway through, the wizard resumes at your last step on the next visit (until you finish or skip all remaining steps).
- **After you finish (or skip all steps):** The wizard is marked complete. Future logins go directly to the Dashboard — the wizard never reappears.
- **Preconfigured installs:** If someone completed onboarding before you logged in, you'll see the Dashboard from the start.

**Dashboard setup checklist v2**

After onboarding, the Dashboard shows a collapsible **setup checklist** with **essential items only**:

| Item | Shown when… | Done when… |
|------|-------------|------------|
| Create your first collection | Always (until done) | At least one collection exists. |
| Add your first entry | Always (until done) | At least one entry exists in a collection. |
| Install a marketplace plugin | No marketplace plugin installed yet | At least one plugin installed from Marketplace. |
| Connect AI | You **skipped** the wizard AI step and have no AI connection yet | An AI provider is connected under Agent Guide → Connections. |

The checklist does **not** include site settings, media upload, or template setup — those are optional extras. If you completed the AI step in the wizard, **Connect AI** never appears on the checklist. You can dismiss individual items (×) or hide the whole checklist.

**Example:** You install Birkly via START_HERE (local path), complete the wizard with project name "Acme Blog", pick the blog use case, skip Marketplace and Connect AI. The Dashboard checklist shows collection, entry, marketplace plugin, and **Connect AI** (because you skipped that wizard step). After you create a collection and entry and install Commerce from Marketplace, only **Connect AI** remains — or you can dismiss it and set up AI later from Settings.

**For administrators**

You can point the Help content to Birkly Docs (or a built copy) so the in-admin Help stays current. For organization-specific instructions ("always set up the Newsletter collection first"), add them to your internal wiki or a custom admin Help page.

## Advanced Users

**Help:** Runtime manifest from `GET /api/help_manifest.php` (merges `docs/help-manifest.json` + active plugin topics). **Live Birkly-Docs:** `settings/help_manifest.json` may set `docs_base_url` and `remote_manifest_url` (default public: `https://docs.birkly.com/help-manifest.json`). `admin/js/help.js` fetches remote manifest when configured; on failure uses bundled core manifest. Topic `docs_path` resolves to `{docs_base_url}/{docs_path}` for **Read full doc** links. Snapshots: `/admin/docs/snapshots/*.md` or `api/plugin_doc.php` for plugins. Plugin topics appear under **Plugin extensions** nav (`section: plugins`, `source: plugin`). No npm build in CMS.

**First-run:** `START_HERE.html` + `START_HERE.txt` ship in the release zip root (`build-cms-release.sh`). Folder detection via `api/setup_probe.php` (read-only). Local path links to `./start-server.sh`; cloud path shows FTP/upload checklist. **Start setup** deep-links to `/admin/?page=onboarding&install=local|cloud`.

**Onboarding v2:** State per user in `users/onboarding/{key}.json`; site-level `install_path` in `settings/onboarding.json` (from START_HERE `?install=` or wizard). Progress persisted via `api/onboarding.php?action=update_progress` (`last_step`, `ai_skipped`). Wizard sets `setup_wizard_completed` on finish or full skip. Steps: `step-first-user` (0), `step-welcome`, `step-install-context`, `step-site-info`, `step-use-case`, `step-marketplace`, `step-connect-ai` (inline BYOAI: provider → key → test → role; `require_approval` default true). Redirect to onboarding when `setup_wizard_completed` is false.

**Dashboard checklist v2:** `api/admin_templates.php` → `getDashboardData()`. Essential IDs: `first_collection`, `first_entry`, `install_marketplace_plugins` (conditional). **Connect AI** (`connect_ai`) appended only when `ai_skipped === true` and no AI connection exists — never a default essential item (P19-D4 / P5.6-D locked). Removed: `site_setup_done`, `configure_settings`, `upload_media`, `create_template`, `setup_complete`. Dismiss per item (`dismiss_checklist_item`) and hide panel (`hide_checklist`) persist in onboarding progress. Local `install_path` adds localhost hints on checklist copy.

## Related

- [Getting started](../00-overview/getting-started.md)
- [DOCS_ARCHITECTURE.md](../DOCS_ARCHITECTURE.md) (for maintainers)
- [Dashboard](dashboard.md)
- [AI connections](ai-connections.md)
- [Settings](settings.md)
