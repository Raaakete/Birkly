# 02 — Solution

## What Birkly is

**Birkly** is a flat-file, modular, **zero-runtime-dependency** content management platform. Upload PHP files to a web server — no database, no npm install, no container required for production.

Core positioning (from product values and marketing guide):

- **Your content, your server, your rules** — no mandatory SaaS subscription.
- **API-first** — admin, public sites, and integrations share the same content layer.
- **UI decoupled from logic** — redesign admin or frontends without breaking features.
- **Self-contained extensions** — fieldtypes and plugins auto-discovered; no core patching to add capabilities.

## How Birkly solves the trade-off

| Customer need | Birkly answer |
|---------------|---------------|
| Ownership & portability | JSON/Markdown flat files; Git-friendly backups; standard PHP hosting |
| Simplicity for editors | Browser admin; collections, entries, roles, workflows |
| Power for developers | Public API, templating language, webhooks, MCP/AI hooks |
| Extensibility without chaos | Plugin + fieldtype architecture; **Birkly Marketplace** for distribution |
| Commerce & events | First-party plugins (Commerce, Events) installable via marketplace |
| Visual site building | **Studio** plugin — in-CMS IDE and production builder (P22–P25 shipped) |

## Platform + ecosystem

Birkly is not only a CMS zip — it is a **platform**:

1. **Birkly core** — CMS runtime, admin, API, templating.
2. **Birkly Marketplace** — separate PHP app: provider onboarding, Stripe Connect, catalog, licenses, QC pipeline.
3. **Reference implementations** — Birkly-Website (marketing), kekaba (client site), Birkly-Docs (documentation SSOT).

## Stage honesty

Birkly is **pre-/early beta**: feature-rich on `main`, invite/waitlist positioning on the marketing site, and human go-live items (DNS, Stripe live mode, legal) still pending per Phase 28 planning. The solution is **built**; broad market launch is **in progress**.
