# P4 browser QA checklist

Used by qa-verifier for P4-M2 and final P4-M11 matrix re-run.

## Onboarding (P4-M2)

- [ ] `php scripts/reset-onboarding.php --keep-entries`
- [ ] Login → wizard loads (no template error)
- [ ] Complete wizard OR skip → dashboard checklist correct
- [ ] CSRF on wizard steps (no 403 on valid session)

## Admin routing (P4-M2)

- [ ] `?page=automation` loads automation plugin UI
- [ ] `?page=site_users` (or plugin route) loads user plugin UI
- [ ] `?page=settings` tabs including AI Connections

## BYOAI (P4-M7)

- [ ] Connection wizard: invalid key shows clear error
- [ ] UI states API key ≠ ChatGPT Plus / Claude Pro subscription
- [ ] Chat with valid connection: read action works
- [ ] Write action → approval queue
- [ ] Deleted connection not selectable in header picker

## MCP inbound (P4-M4/M6)

- [ ] Settings → MCP Connections tab visible without outbound AI row
- [ ] Create endpoint → key shown once → copy snippets work
- [ ] `curl`/bridge `tools/list` with viewer key succeeds
- [ ] Write tool creates approval record (editor key + require_approval)

## Regression

- [ ] Onboarding CSS loads (no 404 onboarding.css)
- [ ] ContentEditor entry editor still loads (template safe renderer)
