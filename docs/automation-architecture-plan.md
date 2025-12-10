# Automation Architecture Plan

## Table of Contents
1. Goals & Principles
2. UX & Navigation
3. Block Model (Triggers → Conditions → Actions)
4. Core Block Catalog (Triggers, Conditions, Actions)
5. Plugin Block Examples
6. Use Cases (Core + Cross-Plugin + System)
7. Execution Architecture & Safety
8. Data & Storage
9. UI/Builder Experience
10. Phased Delivery
11. Placement

---

## 1) Goals & Principles
- Simple, Lego-style automation builder (drag/drop blocks).
- Global-first: all automations managed from a dedicated page; triggers can optionally filter by collection.
- Pluggable: plugins can add triggers/actions.
- Safe: rate limits, loop prevention, permissions, audits.
- Non-breaking: failed blocks don’t crash core; graceful skip/log.

---

## 2) UX & Navigation
- **Dedicated Automation page (global)**: view/search/edit all automations; run tests; see logs.
- **Creation flow**:
  1) Choose trigger (optionally select collection in trigger config when applicable).
  2) Add optional conditions.
  3) Add one or more actions; order actions; set stop-on-failure flag.
  4) Test with sample data; save/enable toggle.
- **Status/monitoring**: enable/disable, last run, last error, recent runs.

---

## 3) Block Model (Triggers → Conditions → Actions)
- **Trigger**: event that starts the flow (e.g., entry_created).
- **Conditions** (optional): filter checks (field/status/collection/user/tier/time).
- **Actions**: one or more steps (email, webhook, set status, create entry, etc.).
- **Data context**: `trigger`, `entry`, `collection`, `user`, `now`, `env`, plus plugin-provided context.
- **Templates**: allow `{{entry.title}}`, `{{entry.id}}`, `{{user.email}}`, etc. in actions/payloads.

---

## 4) Core Block Catalog (Triggers, Conditions, Actions)
### Core Triggers (authoritative start)
- Entry lifecycle: `entry_created`, `entry_updated`, `entry_deleted`
- Status transitions: `status_changed` with from/to (e.g., draft→published, published→archived)
- Scheduled: cron-like (e.g., daily at 09:00)
- Webhook/incoming HTTP (optional/phase 2)
- User events (if user plugin present): `user_created`, `user_login`, `user_deleted`

### Core Conditions
- Field match: equals/contains/regex/numeric/date comparisons
- Status check
- Collection filter (within trigger/condition config)
- User/tier/role (if provided by user plugin)
- Rate/limit guard (e.g., max N emails/hour per automation or per collection)

### Core Actions (initial)
- Email (to static or templated address)
- Webhook (POST/GET with templated payload)
- Slack/Teams (webhook-based)
- Set field / update status
- Create related entry (append to another collection)
- Call plugin action (registered action)
- Log only (for testing)
- (Phase 2) SMS/WhatsApp via plugin; AI moderation flag

### Core Data Context
- `trigger`, `entry`, `collection`, `now`, `env`
- Optional: `user` (if user plugin), plugin-enriched keys (e.g., `payment`, `ai`)

---

## 5) Plugin Block Examples
- **Plugin triggers** (examples):
  - Payment plugin: `payment_succeeded`, `payment_failed`, `subscription_renewed`
  - User plugin: `user_upgraded_tier`, `user_password_reset`
  - AI/moderation plugin: `content_flagged`
  - Monitoring plugin: `error_rate_exceeded`, `traffic_spike_detected`
- **Plugin actions** (examples):
  - User plugin: `grant_user_tier`, `assign_role`, `send_user_message`
  - Payment plugin: `issue_license`, `send_invoice`, `start_subscription`
  - Shop plugin: `adjust_inventory`, `create_order`, `notify_ops`
  - Analytics/search plugin: `index_entry`, `purge_cache`
- **Context enrichment**: plugins add context keys (e.g., `{{payment.amount}}`, `{{user.tier}}`, `{{ai.score}}`, `{{monitoring.error_rate}}`).
- **UI palette**: plugin blocks appear under plugin namespace in the builder palette.
- **Permissions/denylist**: visibility/execution gated by plugin capabilities and global denylist; blocks hidden if not permitted.

---

## 6) Use Cases (Core + Cross-Plugin + System)
- Contact form → email + webhook to CRM.
- New comment/review → moderation email; auto-flag if rating < X.
- Status to “published” → ping search index/webhook; post to Slack.
- Daily digest of new entries.
- User signup (user plugin) → welcome email; create profile entry; assign tier.
- Payment succeeded (payment plugin) → upgrade tier; send receipt; create license entry.
- Low stock (shop plugin) → alert ops; auto-reorder via webhook.
- AI moderation (plugin) → flag/hide content; notify moderator.
- Admin role change → security alert; log to audit collection.
- Scheduled reminders for events; housekeeping jobs.

---

## 7) Execution Architecture & Safety
- **Definition layer**: Automation model (trigger + optional collection filter + conditions[] + actions[] + flags + metadata); version/revision-able.
- **Registry layer**: Catalog of blocks (core + plugin) with schemas, required capabilities, scopes.
- **Execution layer**:
  - Dispatch: triggers fire; match automations by trigger (and collection filter if set), then evaluate conditions.
  - Queue: enqueue actions; worker executes sequentially per run (or controlled parallelism) to avoid blocking UI/API.
  - Retries/backoff: for transient failures (webhooks/email); idempotency keys per run.
  - Loop prevention: “don’t retrigger from actions” flag; tag origin to avoid cycles.
  - Rate limits: per action type and per automation to prevent spam/exfiltration.
  - Error handling: per-action isolation; configurable stop/continue; always log.
- **Template layer**: Safe templating with whitelisted context variables; validate on save; preview/test with sample data.
- **Safety/observability**: per-run logs (status, errors, timestamps); surface in UI; queue/worker health checks.

---

## 8) Data & Storage
- **Automation definition**: name, enabled, trigger type/config, optional collection filter, conditions array, actions array (ordered), stop-on-failure flag, created/updated by, version/revision.
- **Execution log**: automation_id, trigger context, action results, errors, timestamps, runtime.
- **Templates**: stored with definitions; validated on save.

---

## 9) UI/Builder Experience
- Palette: Triggers / Conditions / Actions (core + plugin-contributed).
- Canvas: drag blocks, connect trigger → conditions → actions.
- Config panel: edit selected block (emails, URLs, templates, status values, etc.).
- Validation: required fields, URL/email validation, template variable checks.
- Test mode: run with sample data and see payload/outputs/logs.
- Accessibility: keyboard navigation; simple forms underneath drag/drop for fallback.

---

## 10) Phased Delivery
**Phase 1 (MVP)**: Triggers (entry_created/updated/status_changed), collection filter; conditions (field/status); actions (email, webhook, log, set status/field); async queue; logs; builder UI.

**Phase 2**: Status transition-specific triggers; create-related-entry action; scheduled triggers; retries/backoff; test mode; loop guard.

**Phase 3**: Plugin actions/triggers; rate limits; action priorities; Slack/Teams; user events (with user plugin); AI moderation hook.

**Phase 4**: Webhook inbound triggers; richer templates; advanced conditions (regex, JSON path); versioning of automations.

---

## Placement
- Dedicated Automation page (global). Triggers/conditions can include a collection filter where relevant.

