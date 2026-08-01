# Slack, Teams & webhooks

Automation does **not** use OAuth apps for Slack/Teams. Delivery is “paste an Incoming Webhook URL into the action.”

## Slack

1. In Slack, create an **Incoming Webhook** for the target channel.
2. Copy the URL (`https://hooks.slack.com/services/...`).
3. In Automation editor, add a **Slack** action.
4. Paste the URL into the webhook URL field; set the message body (templates allowed).
5. Save → **Confirm & Run Live** (Test will not post).

## Microsoft Teams

1. In Teams, add an Incoming Webhook connector to the channel.
2. Copy the webhook URL.
3. Use the **Teams** action (or generic Webhook) with that URL.
4. Live-run to verify.

## Generic webhook

Use the **Webhook** action for any HTTPS endpoint:

- Method: POST / PUT / GET
- URL: must be public HTTP(S) on port 80 or 443
- Payload: JSON text; may include `{{entry.title}}` style templates

### Security restrictions

Automation rejects unsafe targets, including:

- `localhost` / private IP ranges
- URLs with embedded credentials
- Non-HTTP(S) schemes
- Unusual ports (not 80/443)

## Setup checklist item

Setup → **Outbound HTTP (curl)** only checks that PHP can make outbound requests. It does **not** store Slack credentials. Each workflow carries its own webhook URL.

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| Dry-run succeeded, channel quiet | Expected — use Confirm & Run Live |
| URL rejected | Use a public https webhook URL |
| curl missing | Install/enable PHP curl on the host |
| 4xx/5xx from Slack | Regenerate webhook; check payload shape |
