# Automation plugin

Deactivatable workflow automation for Birkly (Phase 3 P3-M6/M7). When disabled, the plugin registers **no** hooks, sidebar entry, or API routes.

## Layout

```
plugins/automation/
├── plugin.json           # Manifest, navigation, hooks
├── plugin.php            # Bootstrap, admin page registration
├── init.php              # Block registration on activate
├── automation.php        # Core helpers (enqueue, registry)
├── executor.php          # Run workflows
├── queue.php             # Async execution queue
├── registry.php          # Trigger/condition/action blocks
├── blocks/
│   ├── triggers.php
│   ├── triggers_scheduled.php
│   ├── triggers_webhook.php
│   ├── conditions.php
│   └── actions.php
├── admin/
│   ├── automation.html
│   └── automation.js
└── api/
    └── automation.php    # Plugin-scoped REST (when active)
```

Platform-level webhook entry remains at `api/automation_webhook.php` (routes to active plugin).

## Activation behaviour

| State | Behaviour |
|-------|-----------|
| **Active** | Sidebar **Automation** page (`?page=automation`), blocks registered, entry hooks call `enqueue_automation()`, API endpoints live |
| **Inactive** | No admin page, no hooks, no automation API — core CMS unaffected |

## Concepts

- **Workflow** — JSON definition: trigger + optional conditions + ordered actions.
- **Trigger** — Entry created/updated/published, schedule (cron), webhook inbound, user events (when user plugin fires hooks).
- **Condition** — Filter on payload (field values, time window).
- **Action** — HTTP request, Slack message, create/update entry, email (extensible via `automation_blocks_register` hook).

Storage: `content/automation/` or plugin data dir (workflows + execution logs).

## Admin

1. **Settings → Plugins** — enable **Automation**.
2. Open **Automation** in sidebar.
3. Create workflow → pick trigger → add conditions/actions → enable.
4. **Logs** tab shows executions, errors, and payloads (redacted secrets).

## API (when plugin active)

| Endpoint | Purpose |
|----------|---------|
| `GET api/automation.php?action=list` | List workflows |
| `POST api/automation.php` | CRUD workflows |
| `POST api/automation_webhook.php?workflow={id}` | Inbound webhook trigger |

Requires admin session + `manage_content` (or plugin permission in `plugin.json`).

## Extending

Plugins register blocks via hook:

```php
register_hook('automation_blocks_register', function ($registry) {
    $registry->registerAction('my_action', 'My label', function ($context, $config) {
        // ...
    });
});
```

## Tests

```bash
php tests/automation_plugin_test.php
# or full suite:
php tests/run.php
```

## Related docs

- Birkly-Docs: `05-automation/*`
- Internal: `docs/automation-system.md`, `docs/automation-architecture-plan.md`
- Roadmap: P3-M6 (extract), P3-M7 (E2E)

## Values check

- Self-contained plugin — no required core edits when deactivated
- Same permission model as core (`manage_content` baseline)
