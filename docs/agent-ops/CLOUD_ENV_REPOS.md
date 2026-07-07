# Adding repositories to the Cursor Cloud environment

Cloud agents only see repositories that are **attached to the environment** in the Cursor dashboard. Committing code to GitHub is not enough — the VM must clone the repo at session start.

## Current platform repos (expected)

| Repo | Purpose |
|------|---------|
| `Raaakete/Birkly` | CMS core |
| `Raaakete/Birkly-Docs` | Documentation |
| `Raaakete/Birkly-Website` | Marketing site |
| `Raaakete/kekaba` | Reference client site |
| `Raaakete/Birkly-Marketplace` | Marketplace app (Phase 9+) |

On the VM, repos usually appear as siblings, e.g. `/agent/repos/Birkly`, `/agent/repos/Birkly-Marketplace`. Run `ls` at session start — paths may vary.

## Step-by-step: add a new repo

### 1. GitHub — grant Cursor access

1. Open **GitHub** → **Settings** → **Applications** → **Cursor** (or install [Cursor GitHub App](https://github.com/apps/cursor) if not present).
2. Under **Repository access**, choose **All repositories** or select **`Raaakete/Birkly-Marketplace`** explicitly.
3. Ensure the repo exists at `https://github.com/Raaakete/Birkly-Marketplace` and you can open it in the browser.

Private repos return `Repository not found` to agents without access — fix GitHub app permissions first.

### 2. Cursor dashboard — edit the cloud environment

1. Open **[Cursor Cloud Agents](https://cursor.com/agents)** (or **Cursor Settings → Cloud Agents → Environments**).
2. Select the **existing Birkly multi-repo environment** (the one that already has Birkly, kekaba, Docs, Website).
3. Click **Edit environment** (or **Configure**).
4. Under **Repositories**, click **Add repository** / **Select multiple**.
5. Add **`Raaakete/Birkly-Marketplace`** · branch **`main`**.
6. Save the environment.

Docs: [Cloud Environment Setup](https://cursor.com/docs/cloud-agent/setup) — § Multi-repo environments.

### 3. Secrets (marketplace phase)

Add environment-scoped secrets in the same dashboard when implementing payments:

| Secret | Purpose |
|--------|---------|
| `STRIPE_SECRET_KEY` | Marketplace Stripe platform key |
| `STRIPE_WEBHOOK_SECRET` | Connect webhooks |
| `MARKETPLACE_SESSION_SECRET` | Marketplace app sessions |

CMS repo secrets (`RESEND_API_KEY`, etc.) stay unchanged.

### 4. Start a **new** cloud agent session

Existing running agents **do not** pick up new repos mid-flight. Start a **new** cloud agent task after saving the environment.

### 5. Verify (agent smoke check)

Ask the agent (or run yourself on the VM):

```bash
ls /agent/repos/
# Expect: Birkly  Birkly-Docs  Birkly-Marketplace  Birkly-Website  kekaba

git -C /agent/repos/Birkly-Marketplace status
```

If `Birkly-Marketplace` is missing, the environment was not updated or the session was not restarted.

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| `Repository not found` on clone | GitHub app lacks access to private repo |
| Only one repo on VM | Multi-repo environment not selected; create/edit environment with all repos |
| Multi-repo setup fails immediately | Known intermittent GitLab/GitHub UI bug — retry or use web dashboard “Select multiple” |
| Agent still on old layout | Start new cloud session after environment save |

## Optional: document in primary repo

After adding repos, update `AGENTS.md` repository table so agents know the fifth repo exists.

**Human action:** Adding repos is dashboard-only — agents cannot attach repositories themselves.
