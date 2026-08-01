# Automation plugin release-readiness plan

Date: 2026-07-31
Branch: `cursor/plugin-release-readiness-plan-4216`
Source of truth: `/agent/repos/Birkly-Plugin-Automation`
Context consulted read-only: Marketplace P7, P10, and P41 planning notes.

## Executive summary

Automation is not release-ready for a no-backlog marketplace v1. The plugin has a broad foundation - CRUD, a builder UI, node canvas, blocks, queue, scheduler, dry-run, docs shelf registration, and official marketplace metadata - but several production-blocking gaps remain across security, runtime correctness, first-use UX, packaging, docs, and verification.

Release status: red.

Severity count from this audit:

- P0: 14 blockers before marketplace release.
- P1: 18 required polish or reliability items before release candidate.
- P2: 10 launch-quality improvements that can land during stabilization if P0/P1 stay green.

Highest-risk themes:

1. Cron/queue processing is not safely authenticated or consistently usable.
2. Trigger block handlers are not consistently evaluated, so configured trigger filters can be ignored.
3. Starter templates and canvas defaults reference block IDs that are not registered.
4. Dry-run/test/live-run semantics are inconsistent and can mutate content in paths described as tests.
5. API read and mutation permissions need a full auth/CSRF pass.
6. Webhook/Slack/Teams actions need SSRF controls, secret handling, and log redaction.
7. There is no local test suite in this source repo despite README test claims.
8. Docs are too thin for marketplace users and currently include misleading cron/test instructions.
9. Admin UI needs a P41 design-system pass, accessibility pass, and i18n pass.
10. Packaging/release artifacts need explicit marketplace verification from this source repo, not CMS `plugins/automation` as source of truth.

## Implementation status

Last updated: 2026-07-31, Wave 2b residual polish.

Done:

- P0-03 Trigger evaluation: registered trigger handlers now run during dispatch; status and webhook filters are covered by plugin-local tests.
- P0-04 Starter workflow block IDs: default webhook template and default canvas now use registered `webhook` and `log` blocks; canvas palette includes built-in content/system/communication groups.
- P0-05 Dry-run/test safety: test/dry-run mode validates all action configs and simulates every action without executing side effects; rate-limit guard skips writes in test/dry-run mode.
- P0-06 Queue retry state: future retry jobs remain pending and queue mutations are protected by a local file lock; stale processing jobs recover after 15 minutes.
- P0-07 Outbound HTTP hardening: webhook, Slack, and Teams actions now reject non-HTTP(S), credentialed, local/private/reserved, unresolved, and non-80/443 targets before curl.
- P0-08 Secret redaction: execution logs redact sensitive context/result fields before persistence.
- P0-09 Source-repo tests: `tests/run.php` creates a temporary CMS-like fixture and covers trigger filters, test-mode simulation, queue retry, outbound URL validation, failure status, and redaction.
- P0-11 Docs repair: README, Help docs, and AI context now document token-protected cron, side-effect-free tests, secure outbound URL rules, source-repo tests, and packaging commands.
- P0-12 Truthful run status: `execute_automation()` now returns failure when any action fails.
- P0-13 Visual QA scripts: `docs/VISUAL-WALKTHROUGH-SCRIPTS.md` defines release-candidate recordings for builder, dry-run, queue, webhook, Slack/webhook safety, and deactivation.
- Wave 2b residual polish: admin cron URLs are masked until reveal, Automation CSS now uses local accent token aliases instead of orphan primary-blue fallbacks, and plugin-local tests cover the source-repo package artifact.

Partially done:

- P0-01/P0-02 API and cron auth: plugin API now classifies cron actions, enforces cron-token checks for queue/schedule processing, requires `manage_content` for admin actions, requires CSRF on write/test/live/dry-run actions, and exposes admin-only `cron_info` for secure CLI/token commands with masked token URLs in admin. Remaining work: browser/API integration tests.
- P0-10 Marketplace package/install proof: `scripts/build-plugin-zip.php` and `docs/MARKETPLACE-PACKAGING-CHECKLIST.md` now build/check the source-repo artifact; `php tests/run.php` verifies package contents/exclusions. Remaining work: fresh CMS/Marketplace install verification.
- P0-14 Release decision gate: release, packaging, progress, and visual QA checklists now exist. Remaining work: execute the gate after full-environment QA.

Still open from original P0 list:

- Full-environment verification for fresh install, Marketplace install/update/deactivate, HTTP auth/CSRF matrix, CMS lifecycle hooks, and recorded visual walkthroughs. See `docs/RELEASE-READINESS-PROGRESS.md`.

## Current state inventory

### Manifest and marketplace metadata

- `plugin.json`
  - Name: `Automation`
  - Version: `1.0.0`
  - Marketplace slug: `birkly-automation`
  - Requires Birkly: `>=0.1.0`
  - Requires PHP: `>=8.1`
  - Permissions: `manage_content`
  - Navigation: `Automation`, page `automation`, inline SVG icon
  - Settings schema: default rate limit per hour
- Missing from source repo: explicit license file, changelog/release notes, packaging script, marketplace screenshots, compatibility matrix, signed checksum manifest, and source-repo test command.

### Plugin bootstrap and activation surface

- `plugin.php`
  - Loads `automation.php` and `init.php`
  - Registers admin page `automation`
  - Registers plugin admin meta for sidebar and page permissions
  - Registers a `plugin_activated` hook to initialize blocks
  - Registers a sample plugin action
  - Registers docs shelf metadata
  - Adds dashboard widget callback
- `init.php`
  - Loads registry, triggers, conditions, actions, executor, queue, scheduler, plugin integration, and optional cache.
- `plugin_integration.php`
  - Provides `automation_blocks_register`, `automation_context`, `broadcast_plugin_event()`, and plugin action registration.
- No clear `plugin_deactivated` behavior beyond core not loading the plugin when inactive. Existing queues, schedule state, rate-limit state, and stored secrets are not actively managed.

### Core storage and runtime

- `automation.php`
  - CRUD for workflows in `settings/automations.json`
  - Execution logs in `settings/automation_logs.json`
  - Dashboard widget summary
- `queue.php`
  - Queue in `settings/automation_queue.json`
  - Pending/processing/completed/failed states
  - Retry metadata and queue clear helper
- `scheduler.php`
  - Scheduled trigger processor and state in `settings/automation_scheduler_state.json`
- `rate_limit.php`
  - Per-automation execution counters in `settings/automation_rate_limits.json`
- `cache.php`
  - Cache wrappers for blocks and automation lists
- `executor.php`
  - Trigger dispatch, condition evaluation, action execution, rate limit check, loop prevention, template rendering.

### API surface

`api/automation.php` exposes:

- `dashboard_summary`
- `list`
- `get`
- `create`
- `update`
- `delete`
- `get_blocks`
- `get_logs`
- `test`
- `process_queue`
- `preview_template`
- `get_context_variables`
- `process_scheduled`
- `get_collections`
- `dry_run`
- `run_live`
- `queue_status`

The API bootstraps through `core/automation/bootstrap.php` and returns 404 JSON when inactive. Marketplace planning also identifies the core shim `api/automation.php` as the graceful gate for unbundled plugin installs.

### Blocks

Triggers:

- `entry_created`
- `entry_updated`
- `entry_deleted`
- `sub_entry_created`
- `sub_entry_updated`
- `sub_entry_deleted`
- `status_changed`
- `scheduled`
- `webhook_inbound`
- `plugin_event`

Conditions:

- `field_match`
- `status_check`
- `collection_filter`
- `regex_match`
- `rate_limit_guard`
- `json_path`

Actions:

- `email`
- `webhook`
- `set_status`
- `set_field`
- `log`
- `create_entry`
- `call_plugin_action`
- `slack`
- `teams`
- `sample_notify`

### Admin UI

- `admin/automation.html`
  - Plugin page shell hooks, Automations tab, Documentation tab, health hint, create/edit modal, canvas/form toggle, logs/dry-run modal.
- `admin/automation.js`
  - Loads automations, blocks, collections, queue health
  - Renders list, empty state, templates, modals
  - Supports duplicate, toggle, delete, logs, dry-run, confirm live run
  - Supports node canvas integration and form fallback
- `admin/automation.css`
  - Dedicated plugin styling with CSS variables and P7/P5.6 comments
  - Still uses inline SVGs and mixed token families.
- `admin/automation_node_editor.js`
  - DOM/SVG node canvas with palette, dragging, inspector, export/import.
- `admin/automation_node_serializer.js`
  - Browser workflow/canvas round-trip and validation.
- `includes/node_serializer.php`
  - PHP equivalent serializer and validation.

### Docs

- `README.md` is useful but overstates tests and cron safety.
- `docs/help-overview.md`, `help-triggers.md`, `help-workflows.md`, and `ai-context.md` are one-line to three-line stubs.
- `docs/register_docs.php` registers help, AI context, and public path.

### Tests

- No `tests/` directory exists in this source repo.
- README references `php tests/automation_plugin_test.php` and `php tests/run.php`, but those paths are absent here.
- Internal Marketplace/CMS tests may exist outside this repo, but this audit treats this repo as plugin source of truth.

## Detailed gap analysis

### Functional correctness

1. Trigger handlers are not consistently evaluated. `execute_automations()` special-cases some trigger config but does not call the trigger block handler. `status_changed` `from`/`to` logic and `webhook_inbound` field filters can be bypassed. Severity: P0.
2. Starter workflows are broken. The empty-state webhook template uses action `http_request`, but the registered action is `webhook`; the new-canvas default uses `log_message`, but the registered action is `log`. Severity: P0.
3. Dry-run/test semantics are inconsistent. `dry_run` is simulation-only, but `test` is labelled dry-run in API comments while `execute_automation()` still executes content-mutating actions in test mode because only email/webhook/Slack/Teams are simulated. Severity: P0.
4. Queue retry logic can strand jobs. Retry delay is checked after a job is marked `processing`; if retry is not due, the job is re-saved as `processing`, not pending. Severity: P0.
5. Scheduled processing is under-wired. README says curl `process_scheduled`, but API auth setup only lists `process_queue` as public; `includes/cron_auth.php` exists but is not used by the API. Severity: P0.
6. `execute_automation()` returns `true` after action errors unless conditions fail. Live-run and logs can present a failed action sequence as overall success. Severity: P1.
7. Action ordering is ambiguous. UI promises ordered actions, but executor sorts by priority and may reorder equal-priority arrays with `usort()`. Severity: P1.
8. Queue/log/rate-limit storage rewrites large JSON files without locking. Concurrent admin saves, queue processors, or hooks can drop jobs/logs. Severity: P1.
9. Scheduler only supports simple minute/hour/day matching and lacks timezone, missed-run handling, jitter, and duplicate-worker locks. Severity: P1.
10. Context variables are inconsistent. Templates use `{{now}}` in admin examples, while sample context exposes `timestamp`. Severity: P1.
11. Entry mutation actions write storage directly instead of using stable entry lifecycle APIs, risking missed hooks, slug/index updates, validation, and fieldtype normalization. Severity: P1.
12. Webhook inbound trigger filter checks `$context[$filter_field]`, not nested payload fields, which limits real webhook utility. Severity: P1.

### Security and privacy

1. `process_queue` is publicly callable without token verification. `includes/cron_auth.php` exists but is not enforced. Severity: P0.
2. API read endpoints require only a session, not consistently `manage_content`; logs can expose context data, payloads, webhook URLs, user emails, or secrets to any authenticated user if routing allows. Severity: P0.
3. `dry_run`, `test`, `preview_template`, `get_context_variables`, `get_collections`, and `queue_status` need a complete permission/CSRF policy. Severity: P0.
4. Outbound webhooks, Slack, and Teams allow arbitrary URLs. There is no SSRF protection for localhost, private IP ranges, file schemes, metadata services, or redirect chains. Severity: P0.
5. Secrets are stored in workflow JSON and can appear in logs and API responses. Webhook URLs and Slack/Teams URLs need secret fields, masking, and redaction. Severity: P0.
6. `regex_match` accepts arbitrary regex patterns without validation, timeout mitigation, or warning handling. Severity: P1.
7. Execution logs store full context by default, increasing PII exposure and making exports/backup riskier. Severity: P1.
8. Email action uses hard-coded `noreply@birkly.local`, no tenant-configured sender, and no DMARC/SPF guidance. Severity: P1.

### Activation/deactivation and marketplace install safety

1. Deactivation safety is only implicit. The plugin stops loading when inactive, but existing queue/scheduler/rate-limit state remains unmanaged and docs do not explain behavior. Severity: P1.
2. There is no visible activation health checklist for cron token, queue processor, PHP curl/mail availability, or writable settings. Severity: P1.
3. `plugin.json` declares `plugin_deactivated` in hooks but source has no handler. Severity: P1.
4. Marketplace packaging source is not self-verifying. This repo lacks zip build metadata, release checklist, and artifact validation. Severity: P0.
5. No compatibility test proves the plugin installs into a fresh unbundled CMS via Marketplace without treating CMS `Birkly/plugins` as source of truth. Severity: P0.

### Admin UX, visual design, and a11y

1. The first-use path is broken by invalid block IDs in templates/default canvas. Severity: P0.
2. Canvas editing is mouse-first. Dragging, ordering, selection, deletion, and connection management need keyboard support and screen-reader states. Severity: P1.
3. Modal accessibility needs focus trap, Escape close, labelled close buttons, `aria-modal`, and background inert behavior. Severity: P1.
4. UI strings are largely hardcoded in JS and HTML, while only some labels use `{translate ...}`. Severity: P1.
5. P41 design-system alignment is incomplete: inline SVGs remain, `plugin.json` embeds an SVG icon, and CSS mixes mint tokens with fallback blue `--primary-*` tokens. Severity: P1.
6. The node palette currently groups Commerce, Events, User, and Webhooks, but core Content/System/Communication groups are hidden unless they match those sections. This can make built-in triggers/actions disappear from canvas. Severity: P0.
7. The form editor remains more complete than the canvas editor for custom field types such as collection/sub-collection pickers. Severity: P1.
8. Logs/dry-run output lacks filtering, search, pagination, copyable run IDs, and clear failed-step status. Severity: P1.
9. Health hint uses a weak heuristic and points to unauthenticated cron instructions. Severity: P0.
10. Mobile/responsive behavior has only a basic grid collapse; canvas needs explicit small-screen UX. Severity: P2.

### Docs quality

1. README test commands reference absent files. Severity: P0.
2. README cron commands omit token/CLI security and do not mention the available CLI runner. Severity: P0.
3. Help docs are stubs and do not guide an admin through safe setup, examples, failure recovery, or connector secrets. Severity: P1.
4. No marketplace-facing README section explains use cases, screenshots, permissions, data storage, outbound network behavior, or privacy implications. Severity: P1.
5. No developer docs define block schema contracts, trigger context shape, or third-party extension examples with tests. Severity: P1.

### Performance and operations

1. Logs retain 10,000 full entries in one JSON file; every write rewrites the file. Severity: P1.
2. Queue retention keeps completed jobs unless manually cleared; no scheduled cleanup is wired. Severity: P1.
3. Dashboard summary reads logs, automations, and queue on every widget refresh. Severity: P2.
4. There is no operational status page for last queue run, last scheduled run, failures, disabled actions, or cron token. Severity: P1.

### Internationalization

1. Most JS-created labels, alerts, confirm text, templates, empty states, and modal content are hardcoded English. Severity: P1.
2. Template examples and docs do not explain language-specific workflows or locale-safe dates. Severity: P2.

## Prioritized backlog with acceptance criteria

### P0 blockers

P0-01: Enforce cron authentication and make scheduled processing usable.

- Wire `automation_verify_cron_request()` into `process_queue` and `process_scheduled`.
- Allow CLI runner without HTTP auth.
- Add admin UI to display/copy cron command with token masked/reveal-on-demand.
- Acceptance: HTTP cron calls without token return 403; token calls process queue/schedules; CLI works; README/help use secure examples only.

P0-02: Apply strict permission and CSRF policy to every API action.

- Define public, cron, read-admin, and mutate-admin action groups.
- Require `manage_content` for all admin read/write automation endpoints.
- Require CSRF for mutating admin actions and any live execution.
- Acceptance: unauthenticated and low-permission users cannot list workflows, logs, blocks, queue, or collections; automated tests cover each action class.

P0-03: Fix trigger evaluation.

- Call trigger block handlers after type match.
- Preserve special filters only as optimizations, not substitutes for handlers.
- Add tests for `status_changed from/to`, webhook field filters, collection/sub-collection filters, scheduled, and plugin events.
- Acceptance: configured trigger filters are honored in executor and dry-run output.

P0-04: Fix first-use workflow templates and canvas defaults.

- Replace `http_request` with `webhook` and `body` with `payload`.
- Replace `log_message` with `log`.
- Ensure default canvas palette includes Content/System/Communication blocks.
- Acceptance: a new admin can create, save, dry-run, and run a log workflow without editing raw JSON.

P0-05: Make dry-run, test, and live-run semantics safe and explicit.

- Deprecate or rename API `test`; make it no-side-effect by default.
- Ensure all actions, including content-mutating actions, are simulated in dry-run/test mode.
- Live-run must show concrete context and require explicit confirmation.
- Acceptance: dry-run/test never changes content, sends network calls, or writes logs except optional dry-run audit; live-run clearly reports real side effects.

P0-06: Fix queue retry state and add locking.

- Check `retry_after` before marking processing.
- Add file lock/atomic write strategy for queue mutations.
- Add stale-processing recovery.
- Acceptance: retry jobs remain pending until due, multiple processors do not duplicate/drop jobs, stale processing jobs recover.

P0-07: Harden outbound HTTP actions.

- Validate URL scheme and host.
- Block localhost/private/link-local/metadata IPs, unsafe ports, and suspicious redirects.
- Add timeout and response-size limits.
- Acceptance: SSRF test matrix passes; valid HTTPS webhook/Slack/Teams mocks still pass.

P0-08: Secret handling and log redaction.

- Mark `webhook_url`, Slack/Teams URLs, headers/tokens, and sensitive payload fields as secrets.
- Mask secrets in admin list, forms, API responses, and logs.
- Do not log full context by default.
- Acceptance: saved workflows never expose full secrets in API/log UI; redaction tests cover nested context and action configs.

P0-09: Add source-repo automated tests.

- Create a plugin-local test suite using temporary settings/content storage.
- Cover registry, serializers, template render, triggers, conditions, actions, queue, scheduler, rate limits, API auth.
- Acceptance: one documented command runs from this repo and passes in CI; README test section is accurate.

P0-10: Prove marketplace install/package readiness from this repo.

- Add release artifact manifest/checklist for plugin zip content, excluded runtime data, version, checksum, docs, screenshots.
- Verify source repo can be packaged and installed into a fresh CMS without using CMS `Birkly/plugins` as SoT.
- Acceptance: release candidate artifact installs, activates, deactivates, and uninstalls cleanly in a fresh CMS.

P0-11: Repair docs that can cause unsafe operation.

- Replace insecure curl examples.
- Document CLI runner, token runner, schedule runner, permissions, data storage, and deactivation behavior.
- Acceptance: README/help docs are accurate to code and include admin-safe setup.

P0-12: Make run status truthful.

- Return `false` or structured partial-failure status when actions fail.
- Expose action-by-action status in API and UI.
- Acceptance: live run with failing webhook shows failed, logs errors, and does not report success.

P0-13: Add visual QA scripts and artifacts to the release process.

- Define repeatable browser walkthroughs for builder, dry-run, webhook, schedule, and Slack mock.
- Acceptance: release checklist requires stored videos/screenshots from clean run.

P0-14: Establish release decision gate.

- No P0/P1 open at RC cut.
- QA owner signs functional, security, UX, docs, packaging, and marketplace sections.
- Acceptance: release checklist can be completed without waivers.

### P1 release-candidate requirements

P1-01: Implement explicit deactivation behavior and docs.

- Acceptance: deactivating stops hooks/API/admin UI, leaves data intact, displays inactive CTA, and documents queue/scheduler state.

P1-02: Add activation health checks.

- Acceptance: admin shows curl/mail availability, writable storage, cron token status, last queue run, last scheduled run, pending failures.

P1-03: Preserve action order.

- Acceptance: UI order is runtime order unless a documented priority override is explicitly enabled and visible.

P1-04: Move content mutations through stable CMS APIs.

- Acceptance: create/set-field/set-status actions trigger lifecycle hooks, validation, slugs/indexes, and storage paths exactly like admin entry saves.

P1-05: Add event and plugin integration contract tests.

- Acceptance: Commerce/Events/User plugin block registration works when those plugins are active and fails gracefully when absent.

P1-06: Improve logs and run history.

- Acceptance: logs have run ID, workflow name, trigger, condition result, action result, runtime, redacted context, filters, pagination, and export.

P1-07: P41 design-system pass.

- Acceptance: use shared plugin-page styles, icon helper where available, mint tokens, no blue orphan primary fallbacks, consistent empty states/status badges.

P1-08: Accessibility pass.

- Acceptance: keyboard-only workflow build works, modal focus trap passes, screen-reader labels are meaningful, axe/browser checks show no critical issues.

P1-09: i18n pass.

- Acceptance: admin JS and HTML strings route through the translation system or a documented plugin dictionary.

P1-10: Expand help and AI docs.

- Acceptance: Help includes overview, getting started, triggers, conditions, actions, queue/scheduler, connectors, troubleshooting, developer extension, and privacy topics.

P1-11: Add webhook inbound endpoint validation coverage.

- Acceptance: inbound webhook route proves ID matching, optional payload filtering, auth/rate limits, and replay handling.

P1-12: Add scheduler timezone and missed-run policy.

- Acceptance: admin can choose timezone or inherits site timezone; missed runs are skipped/caught up per documented setting.

P1-13: Add queue cleanup and retention controls.

- Acceptance: completed/failed job retention is configurable and cleanup is scheduled.

P1-14: Add settings UI for rate limits.

- Acceptance: default and per-workflow limits can be viewed and changed by admins.

P1-15: Validate JSON fields in UI.

- Acceptance: webhook payload, create-entry fields, plugin action parameters, and Slack attachments validate before save.

P1-16: Replace direct `confirm()` prompts.

- Acceptance: destructive/live actions use styled admin confirmation dialogs with clear consequences.

P1-17: Add privacy/data-access docs.

- Acceptance: marketplace listing explains data stored in settings JSON, logs, outbound network calls, and secret handling.

P1-18: Add compatibility matrix.

- Acceptance: release notes state supported CMS/PHP versions, required PHP extensions, and optional mail/curl requirements.

### P2 polish

P2-01: Add workflow search/filter and tags.
P2-02: Add run-now with custom JSON context editor.
P2-03: Add import/export workflow library.
P2-04: Add marketplace recipe gallery for common workflows.
P2-05: Add dashboard charts for executions and failures.
P2-06: Add Slack/Teams test-connection buttons.
P2-07: Add reusable variable picker/autocomplete.
P2-08: Add mobile-specific read-only canvas or linear editor.
P2-09: Add advanced schedule previews.
P2-10: Add developer examples for third-party block packs.

## Target product definition for marketplace v1

Marketplace v1 should ship as a polished official free plugin that lets a site admin automate routine CMS operations without code and lets other official plugins register automation blocks.

### Covered use cases

1. Content workflow notifications
   - When an entry is created, updated, deleted, or published, send email/Slack/Teams/webhook or write a log.
2. Content moderation workflows
   - When an entry changes status, run conditions and set status/field or notify reviewers.
3. Sub-entry workflows
   - When comments, registrations, or nested rows are created/updated/deleted, notify or call plugin actions.
4. Scheduled workflows
   - Run hourly/daily/weekly/monthly/custom schedules with reliable queue execution.
5. Inbound webhook workflows
   - Receive external events via a secure webhook endpoint and route by webhook ID and payload filters.
6. Plugin event workflows
   - Commerce, Events, User, Studio, and third-party plugins can broadcast events and register custom actions.
7. Safe testing
   - Admins can dry-run a workflow with clear step output and no side effects, then live-run with explicit confirmation.
8. Operational monitoring
   - Admins can see queue health, recent runs, failures, last cron execution, and setup guidance.
9. Marketplace-ready install lifecycle
   - Fresh CMS can install, activate, deactivate, update, and remove the plugin without bundled core assumptions.
10. Documentation and help
   - Help, AI context, README, and marketplace listing explain setup, examples, security, troubleshooting, and extension APIs.

### Non-goals for v1

- Branching workflow graph execution beyond a validated linear sequence.
- Long-running visual workflow analytics beyond run history and health.
- Enterprise secret vault integration beyond encrypted/masked local storage if core supports it.

## Execution plan

### M0 - Audit closure and release gate setup

Dependencies: this plan.

Tasks:

1. Confirm source repo is canonical for packaging.
2. Add release checklist and test matrix scaffolds.
3. Decide P0/P1 owners and target release date.
4. Add issue/backlog tracking entries for every P0/P1.

Acceptance:

- Backlog is complete, no untracked intentional backlog remains, and owners are assigned.

### M1 - Security, auth, and install lifecycle

Dependencies: M0.

Tasks:

1. API permission/CSRF policy.
2. Cron token/CLI processing.
3. SSRF controls and secret masking.
4. Activation/deactivation behavior.
5. Marketplace artifact checks.

Acceptance:

- Security tests pass; unauthenticated/low-permission probes fail safely; plugin installs and deactivates cleanly.

### M2 - Runtime correctness

Dependencies: M1 for safe test harness.

Tasks:

1. Trigger handler evaluation.
2. Queue retry/locking/stale recovery.
3. Scheduler behavior and timezone policy.
4. Truthful execution result model.
5. Content action API alignment.

Acceptance:

- Unit/integration tests cover all core trigger, queue, scheduler, and action paths.

### M3 - Builder UX and design system

Dependencies: M2 runtime contracts.

Tasks:

1. Fix starter templates/default canvas.
2. Complete palette categories and collection/sub-collection picker parity.
3. P41 icon/token cleanup.
4. Accessibility and keyboard workflows.
5. i18n pass.

Acceptance:

- Admin can build workflows by canvas or form in light/dark mode; keyboard-only core path works; no broken default workflows.

### M4 - Connectors, docs, and observability

Dependencies: M1/M2.

Tasks:

1. Harden email/webhook/Slack/Teams actions.
2. Add logs/run history with redaction and filters.
3. Add activation health/status panel.
4. Write full README/help/AI/marketplace docs.

Acceptance:

- Admin can configure, test, and troubleshoot every built-in connector with docs matching behavior.

### M5 - Automated and visual QA

Dependencies: M1-M4.

Tasks:

1. Run plugin-local tests.
2. Run CMS integration tests with plugin installed from packaged artifact.
3. Run manual browser QA in light/dark, desktop/mobile.
4. Capture visual walkthrough videos.
5. Execute security regression probes.

Acceptance:

- Test matrix is green and visual artifacts show builder, dry-run, webhook, scheduled, and Slack mock flows.

### M6 - Release candidate and marketplace publish gate

Dependencies: M5 green.

Tasks:

1. Build release candidate zip.
2. Verify manifest, checksums, version, docs, screenshots, and listing metadata.
3. Fresh install/update/deactivate smoke.
4. Final security/design/docs review.
5. Release decision.

Acceptance:

- Definition of Done is fully checked with no P0/P1 open and no undocumented waivers.

Recommended milestone count: 7 milestones, M0-M6.

## Comprehensive review and testing plan

### Automated unit tests

Run from plugin source repo with temporary storage roots.

- Manifest parse and version/permission checks.
- Block registry registers all expected triggers/conditions/actions.
- Trigger handlers:
  - entry collection filters
  - sub-entry collection/sub-collection filters
  - status `from`/`to`
  - webhook ID and payload filter
  - plugin event name
  - scheduled match/no-match
- Conditions:
  - field match operators
  - status check
  - collection filter
  - regex validation/error handling
  - rate-limit guard
  - JSON path
- Actions:
  - email simulated
  - webhook mocked HTTP
  - Slack/Teams mocked HTTP
  - set status/field through CMS API
  - create entry through CMS API
  - log action
  - plugin action hook
- Template preview and validation.
- Queue:
  - enqueue
  - process success
  - retry delay
  - max attempts
  - stale processing recovery
  - retention cleanup
- Scheduler:
  - hourly/daily/weekly/monthly/custom
  - timezone behavior
  - duplicate prevention
- Serializers:
  - PHP and JS fixtures round-trip the same workflow.
  - canvas validation catches zero/multiple triggers, no action, cycle.
- Security:
  - auth/CSRF matrix
  - SSRF blocked hosts
  - secret redaction
  - low-permission log access denied.

### Integration tests

Run against a fresh CMS root with Automation installed from a built zip.

1. Fresh CMS with no plugins:
   - `/api/automation.php?action=list` returns 404 JSON, not fatal.
   - `/admin/index.php?page=automation` shows inactive/install CTA if core supports it.
2. Marketplace install:
   - install official `birkly-automation`
   - activate
   - admin page appears
   - docs shelf topic appears
3. Workflow CRUD:
   - create, update, duplicate, enable/disable, delete
   - stored data excludes runtime secrets from API response.
4. Entry lifecycle:
   - create/update/delete/status-change entry fires exactly matching workflows.
5. Sub-entry lifecycle:
   - create/update/delete sub-entry fires exactly matching workflows.
6. Queue:
   - entry trigger enqueues
   - cron token processes
   - logs show run results.
7. Scheduled:
   - schedule due workflow enqueues
   - queue processes it once.
8. Webhook inbound:
   - valid webhook ID triggers workflow
   - invalid ID/filter does not.
9. Slack/webhook:
   - mocked external endpoint receives expected redacted payload.
10. Deactivation:
   - admin/API/hooks stop
   - stored workflows remain
   - reactivation restores UI without duplicate blocks.
11. Update:
   - update zip preserves workflows and secrets.

### Manual QA matrix

Roles:

- Superadmin
- Admin with `manage_content`
- Editor without automation permission
- Anonymous

Browsers/views:

- Chrome desktop light
- Chrome desktop dark
- Firefox desktop light
- Mobile/narrow viewport

Core flows:

- Empty state renders and templates are usable.
- Create log workflow in canvas.
- Create email workflow in form editor.
- Add collection/sub-collection trigger filters.
- Add conditions and ordered actions.
- Save, edit, duplicate, toggle, delete.
- Dry-run and live-run confirmations.
- Logs list, empty logs, failure logs.
- Queue health banner with and without cron configured.
- Documentation tab loads plugin docs.
- Keyboard-only modal/canvas/form path.
- Screen reader spot-check of modal and canvas.

### Visual walkthrough scripts

Each walkthrough should be recorded from a clean test site and stored with the release review pack.

1. Admin builder walkthrough
   - Open Automation
   - Start from empty state
   - Create "Log when entry is published" in node canvas
   - Configure status trigger and log action
   - Save and show list card.
2. Dry-run walkthrough
   - Open saved workflow
   - Run dry-run
   - Show trigger, condition, and action steps
   - Confirm no side effects/log/network calls.
3. Webhook walkthrough
   - Create inbound webhook workflow
   - Show secure webhook URL/token guidance
   - Send mocked webhook
   - Process queue
   - Show successful log.
4. Scheduled walkthrough
   - Create scheduled workflow
   - Show cron command with token masked
   - Run CLI/token processor
   - Show queued and completed run.
5. Slack walkthrough
   - Configure Slack action against mock endpoint
   - Dry-run shows no send
   - Live-run sends to mock endpoint
   - Logs redact webhook URL.
6. Deactivation walkthrough
   - Disable plugin
   - Show admin/API unavailable gracefully
   - Reactivate and show workflows preserved.

## Definition of Done and release checklist

### Functional

- [ ] Every built-in trigger, condition, and action has automated coverage.
- [ ] Starter templates and default canvas save and run.
- [ ] Dry-run has no side effects.
- [ ] Live-run reports partial failures truthfully.
- [ ] Queue and scheduler are reliable under concurrent processing.
- [ ] Activation/deactivation/update preserve data safely.

### Security/privacy

- [ ] API auth and CSRF matrix is green.
- [ ] Cron endpoints require CLI or token.
- [ ] SSRF controls are tested.
- [ ] Secrets are masked and redacted everywhere.
- [ ] Logs avoid full unredacted PII/context by default.
- [ ] Marketplace docs disclose outbound network and stored data.

### UX/design/a11y

- [ ] P41 design-system pass complete.
- [ ] Light and dark visual review complete.
- [ ] Keyboard-only workflow build path works.
- [ ] Modal and canvas accessibility checks pass.
- [ ] i18n strings are externalized or documented.
- [ ] Empty, error, loading, health, and logs states are polished.

### Docs

- [ ] README matches source repo and current commands.
- [ ] Help docs are complete.
- [ ] AI context is actionable.
- [ ] Developer extension docs include examples/tests.
- [ ] Marketplace listing has description, screenshots, permissions, privacy, changelog, compatibility.

### Packaging/release

- [ ] Plugin zip built from `/agent/repos/Birkly-Plugin-Automation`.
- [ ] Runtime data excluded.
- [ ] Version and checksum verified.
- [ ] Fresh CMS marketplace install verified.
- [ ] Update and rollback path verified.
- [ ] No P0 or P1 backlog remains.

### Testing/artifacts

- [ ] Plugin unit tests green.
- [ ] CMS integration tests green.
- [ ] Security probes green.
- [ ] Manual QA matrix signed off.
- [ ] Visual walkthrough videos captured and reviewed.
- [ ] Release review pack attached.

## Risks and open questions

1. Source-of-truth transition risk: Marketplace planning says official plugins were historically retained in CMS for builds, while this task requires this repo as SoT. Confirm the packaging pipeline now consumes this repo.
2. Core hook availability: entry/sub-entry/webhook lifecycle hooks are outside this source repo; integration tests must prove the current CMS emits the contexts expected by Automation.
3. Secret storage capability: if core lacks encrypted settings, decide whether masked plain settings are acceptable for v1 or whether core support is required.
4. Webhook inbound route: README mentions platform-level `api/automation_webhook.php`, but this source repo does not contain it. Verify current CMS route and document it from tested behavior.
5. Mail delivery: email action depends on PHP `mail()`. Decide whether v1 supports only server mail or requires SMTP/provider configuration.
6. Branching workflows: P7 scoped runtime as linear JSON with node view. Confirm marketplace copy does not imply branching if branching is not implemented.
7. Official plugin cross-blocks: Commerce/Events/User blocks are palette targets, but this repo cannot prove their exact block IDs alone. Cross-plugin integration tests are required.
8. Data retention policy: decide default retention for logs, queue jobs, scheduler state, and rate-limit state.
9. Marketplace screenshots/video ownership: decide whether screenshots live in this repo or Marketplace listing data.
10. Release version: manifest says `1.0.0`; decide whether fixes ship as `1.0.0` before public release or bump to `1.0.1` if marketplace already published.

## Recommended release decision

Do not ship the current Automation plugin as marketplace-ready. Proceed with M0-M6 above, with P0 blockers closed before any release-candidate cut and P1 items closed before publish. The current codebase is a strong prototype/beta foundation, but marketplace v1 needs security hardening, correctness fixes, test coverage, docs, and visual QA before it can ship without intentional backlog.
