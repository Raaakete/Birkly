# Examples

Copy these recipes, then adapt collection names and addresses.

## 1) Email when any entry is created

**Trigger:** Entry created (no collection filter)  
**Action:** Send Email

```text
To: editors@yourdomain.com
Subject: {{automation.name}}
Body:
{{trigger_summary}}

Title: {{entry.title}}
Collection: {{collection}}

Open in Birkly:
{{entry_admin_url}}
```

Name the automation something useful (e.g. “Notify editors of new posts”) — that becomes the subject. The body summarizes the trigger (new entry in the collection) and includes an admin link/button to the entry.

**Setup:** SMTP + Send test email first.

## 2) Email only for the `contact` collection

**Trigger:** Entry created → Collection = `contact`  
**Action:** Send Email to your inbox with `{{entry.email}}` and `{{entry.message}}` (field names must match your schema).

## 3) Slack when status becomes published

**Trigger:** Status changed → from `draft` → to `published` (optional collection filter)  
**Action:** Slack with Incoming Webhook URL

```text
Message: Published "{{entry.title}}" in {{collection}}.
```

## 4) Branching: publish path vs everything else

**Trigger:** Entry updated  
**Condition:** Status check = `published`  
**If true:** Slack “Published …”  
**Else:** Log “Updated but not published”

Connect condition **If true** / **Else** ports on the canvas.

## 5) Webhook to an external system

**Trigger:** Entry created in `orders`  
**Action:** Webhook POST to `https://example.com/hooks/birkly`

```json
{
  "title": "{{entry.title}}",
  "id": "{{entry.id}}",
  "collection": "{{collection}}"
}
```

## 6) Prove the runner without email

1. Setup → transport **Log only** → Save  
2. Workflow: Entry created → Send Email (any To/subject)  
3. Create an entry or Run Live  
4. Setup → **Recent sends** should show a row  

Then switch transport to SMTP for real delivery.

## 7) Sub-entry created (e.g. new comment)

**Trigger:** Sub-entry created  
**Config:** parent collection + sub-collection slugs  
**Action:** Email or Slack with parent/child fields from context

## Tips for reliable examples

- Keep one job per workflow while learning
- Prefer collection filters to avoid noisy emails
- Always distinguish dry-run from live
- Check automation Logs after the first live attempt
