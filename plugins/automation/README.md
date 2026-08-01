# Automation plugin

Deactivatable workflow automation for Birkly. When disabled, the plugin registers **no** hooks, sidebar entry, or API routes.

## Layout

```
plugins/automation/
├── plugin.json                # Manifest, navigation, hooks
├── plugin.php                 # Bootstrap, admin page registration
├── init.php                   # Block registration on activate
├── automation.php             # Core helpers (enqueue, registry)
├── executor.php               # Run workflows
├── queue.php                  # Async execution queue
├── rate_limit.php
├── registry.php               # Trigger/condition/action blocks
├── security.php               # Outbound URL validation + redaction helpers
├── scheduler.php
├── template_preview.php
├── blocks/
│   ├── triggers.php           # entry_*, sub_entry_*, status_changed
│   ├── triggers_scheduled.php
│   ├── triggers_webhook.php
│   ├── conditions.php
│   ├── actions.php
│   └── actions_slack.php
├── admin/
│   ├── automation.html
│   ├── automation.js          # Builder UI, pickers, reorder, dry-run
│   └── automation.css         # Dedicated plugin stylesheet (P5.6-M3)
└── api/
    └── automation.php         # Plugin-scoped REST (when active)
```

Platform-level webhook entry remains at `api/automation_webhook.php` (routes to active plugin).

## Activation behaviour

| State | Behaviour |
|-------|-----------|
| **Active** | Sidebar **Automation** page, blocks registered, entry hooks call `enqueue_automation()`, API endpoints live |
| **Inactive** | No admin page, no hooks, no automation API — core CMS unaffected |

## Concepts

- **Workflow** — JSON definition: trigger + optional conditions + ordered actions.
- **Trigger** — Entry created/updated/deleted, sub-entry events, status change, schedule, webhook.
- **Condition** — Filter on payload (field values, time window).
- **Action** — HTTP request, Slack message, create/update entry, email (extensible via `automation_blocks_register` hook).

Storage: `settings/automations.json` (workflows) + `settings/automation_queue.json` (queue) + logs.

## Triggers

| Trigger | Category | Filters |
|---------|----------|---------|
| `entry_created` | Content | `collection` |
| `entry_updated` | Content | `collection` |
| `entry_deleted` | Content | `collection` |
| `status_changed` | Content | `collection`, `from`, `to` |
| `sub_entry_created` | Sub-Content | `collection`, `sub_collection` |
| `sub_entry_updated` | Sub-Content | `collection`, `sub_collection` |
| `sub_entry_deleted` | Sub-Content | `collection`, `sub_collection` |
| `scheduled` | Schedule | `cron_expression` |
| `webhook_inbound` | Webhook | `webhook_id` |

Collection and sub-collection filters use the picker UI in the builder — select from the dropdown or leave empty to match all.

## Admin UX (P5.6-M3)

1. **Settings → Plugins** — enable **Automation**.
2. Open **Automation** in the sidebar.
3. Create workflow → pick trigger → configure collection/sub-collection pickers → add conditions/actions → enable.
4. Use **up/down arrows** on actions to reorder them.
5. **Test** button runs a **dry-run** — step log only, no live actions (D4). "Confirm & Run Live" executes for real.
6. **Duplicate** copies a workflow (disabled by default).
7. **Logs** tab shows execution history, status, runtime, and errors.
8. A **health hint banner** appears when pending queue jobs have never been processed (cron not configured).

### Cron setup (queue processing)

Preferred CLI command (every minute, from the CMS root):

```
* * * * * php plugins/automation/cli/process_queue.php 10
```

HTTP cron calls require the Automation cron token. Admins can copy token-protected URLs from the Automation health banner when queued jobs are pending.

Queue processor:

```
* * * * * curl -s "https://yoursite.com/api/automation.php?action=process_queue&token=YOUR_AUTOMATION_CRON_TOKEN"
```

Scheduled trigger processor:

```
* * * * * curl -s "https://yoursite.com/api/automation.php?action=process_scheduled&token=YOUR_AUTOMATION_CRON_TOKEN"
```

Unauthenticated HTTP cron calls return `403`.

## API (when plugin active)

| Endpoint | Purpose |
|----------|---------|
| `GET ?action=list` | List workflows |
| `GET ?action=get_blocks` | Available triggers/conditions/actions |
| `GET ?action=get_collections` | Collections for picker UI |
| `POST ?action=create` | Create workflow |
| `POST ?action=update` | Update workflow |
| `POST ?action=delete` | Delete workflow |
| `POST ?action=dry_run` | Simulate without executing actions |
| `POST ?action=test` | Side-effect-free test execution |
| `GET ?action=get_logs` | Execution logs |
| `GET ?action=queue_status` | Queue statistics |
| `GET ?action=cron_info` | Admin-only secure cron commands |

Requires admin session + `manage_content` permission, except token-protected cron actions. Mutating/test/live/dry-run POST actions require CSRF.

## Extending

Plugins register blocks via hook:

```php
register_hook('automation_blocks_register', function ($registry) {
    // register actions
});
// Or directly:
register_automation_block('action', 'my_action', [
    'name'    => 'My Action',
    'schema'  => ['url' => ['type' => 'text', 'label' => 'URL', 'required' => true]],
    'handler' => function($config, $context) { /* ... */ }
]);
```

## Tests

```bash
php tests/run.php
```

The test harness creates a temporary CMS-like fixture from this source repo and covers trigger filters, dry-run/test safety, queue retry state, outbound URL validation, failure status, cron token matching, and secret redaction.

## Marketplace packaging

```bash
php scripts/build-plugin-zip.php
```

This writes `build/birkly-automation-<version>.zip` plus a `.sha256` file. See `docs/MARKETPLACE-PACKAGING-CHECKLIST.md`.

## Context pack

Automation is documented in `docs/ai/birkly-context-pack.md` § Automation — covering triggers, context variables, test-run, and API surface.

## Related docs

- Birkly-Docs: `05-automation/*`
- Internal: `docs/automation-system.md`
- Roadmap: P3-M6 (extract), P3-M7 (E2E), P5.6-M3 (power-up)

## Values check

- Self-contained plugin — no required core edits when deactivated
- Same permission model as core (`manage_content` baseline)
- Dry-run default per D4 — live execution requires explicit user confirmation
