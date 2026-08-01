# Automation visual walkthrough scripts

Use these scripts for release-candidate browser recordings. Record from a fresh CMS with Automation installed from the marketplace zip built by this repo.

## 1. Admin builder

1. Open Automation from the admin sidebar.
2. Show empty state and template cards.
3. Create a workflow in Node canvas:
   - trigger: `status_changed`, `to = published`
   - action: `log`, message `Published {{entry.title}}`
4. Save.
5. Show workflow card with enabled badge.

Pass criteria:

- Built-in Content/System/Communication palette sections are visible.
- Workflow saves with no raw JSON edits.
- Card shows correct trigger and action count.

## 2. Dry-run safety

1. Click **Test** on the workflow.
2. Show dry-run modal.
3. Confirm step list renders trigger, conditions, and action.
4. Confirm copy states that no actions were executed.

Pass criteria:

- No content is changed.
- No webhook/email/Slack/Teams request is sent.
- The modal offers live run only after dry-run output.

## 3. Token-protected queue

1. Create or enqueue a pending workflow job.
2. Show the health hint.
3. Show preferred CLI command and token-protected queue/scheduled URLs.
4. Run the CLI processor from a terminal.
5. Refresh Automation logs.

Pass criteria:

- UI does not recommend unauthenticated cron URLs.
- Pending job moves to completed or failed with a clear run log.

## 4. Webhook inbound

1. Create workflow:
   - trigger: `webhook_inbound`
   - webhook ID: `orders`
   - filter: `payload.event = order.created`
   - action: `log`
2. Send a matching webhook to the CMS webhook route.
3. Process queue.
4. Show successful log.
5. Send a non-matching webhook and show it does not run.

Pass criteria:

- Matching payload runs once.
- Non-matching payload does not run.

## 5. Slack/webhook safety

1. Configure Slack or webhook action with a local/private URL.
2. Live-run or process queue.
3. Show rejected URL error.
4. Configure a public mock endpoint.
5. Run live and show success log with secret URL redacted.

Pass criteria:

- Local/private target is blocked before curl.
- Public mock target succeeds.
- Logs never show full webhook URL.

## 6. Deactivation

1. Disable Automation in Settings > Plugins.
2. Open direct Automation admin URL.
3. Call Automation API.
4. Re-enable Automation.
5. Show workflows still present.

Pass criteria:

- Disabled plugin has no active admin/API/hooks.
- Existing workflow data is preserved.
