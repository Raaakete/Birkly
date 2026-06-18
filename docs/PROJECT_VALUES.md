# Birkly Project Values

Human-approved baseline. All agent work and PRs must align with these values. See `docs/agent-ops/REPORTING.md` for the values check on every digest and PR.

## North star

A highly flexible, robust CMS that remains **simple**, **secure**, and **easy to use**.

## Stack (dependency-free core)

- **Allowed in core CMS:** PHP, HTML, CSS, JavaScript only.
- **Not allowed in core CMS:** npm/composer package dependencies, JS/CSS frameworks, or build steps required to run the CMS.
- **Scope note:** `Birkly-Docs` may use npm for documentation builds; client site repos may use their own static tooling. The **platform core** (`Birkly` repo) stays dependency-free.

Evidence: `README.md` — *“zero-dependency… no npm, no database”*; `composer.json` requires PHP only.

## User experience

- **Production:** Upload files → use admin in the browser. **No terminal required.**
- **Local dev only:** `start-server.sh` or `php -S … server-router.php` (documented in `0_START-HERE.html`).

## Architecture — separation of concerns

The codebase is layered so updates in one area do not break others:

| Layer | Responsibility |
|-------|------------------|
| API / core PHP | Data, permissions, storage, business rules |
| Admin templates | Presentation (`admin/templates/`) |
| Client delivery | `birkly-client.js` fetch + render |
| Fieldtypes / plugins | Self-contained extensions, auto-discovered |

Platform changes land in **Birkly first**; client sites embed `Birkly-0.1/` — do not fork CMS logic into site repos (`AGENTS.md`).

## Presentation vs functionality (UI decoupling)

**UI (HTML/CSS/templates) must be decoupled from business logic (API, storage, permissions, rendering engine).**

Goal: **redesign or restyle the UI without breaking features**, and change backend behavior without rewriting templates unless the contract intentionally changes.

| Area | Rule |
|------|------|
| **Admin** | Templates in `admin/templates/`; behavior via API + shared JS hooks — not inline business logic mixed into markup |
| **Public site** | HTML/CSS layout separate from `birkly-client.js` data fetch and template rendering |
| **Fieldtypes / plugins** | Self-contained folders; core provides registry and contracts only |

Canonical reference: `docs/admin-templating.md` — *“Complete separation between admin frontend appearance and functionality… Changes to appearance don't affect functionality.”*

## Self-contained extensions

Fieldtypes and plugins are **fully self-contained**, live in their own folder, and are **auto-detected** by the system — no manual core registration.

| Extension | Discovery | Canonical doc |
|-----------|-----------|---------------|
| Fieldtypes | `src/bootstrap_fieldtypes.php` scans `fieldtypes/*` | `docs/fieldtypes-architecture.md` |
| Plugins | `core/public_bootstrap.php` scans `plugins/*` | `docs/plugin-system-architecture-plan.md` |

Adding a fieldtype or plugin must not require editing core registration code.

## Security and data

- Security by default: auth boundaries, permissions, CSRF — not “open then lock down.”
- Flat-file content; portable backups; Git-friendly where appropriate.
- Do not commit runtime data: `users/`, `cache/`, `logs/`, `temp/`, `media/`, `settings/`, `.env`, `config.php`.

## Documentation

- **Birkly-Docs** is the single source of truth for product documentation.
- Every topic: Summary + Beginner + Advanced Users (`Birkly-Docs/DOCS_ARCHITECTURE.md`).
- Update docs when CMS features ship — not as a parallel project during platform stabilization.

## Anti-patterns (do not ship)

| Anti-pattern | Why it violates values |
|--------------|------------------------|
| npm/build step in core CMS | Breaks dependency-free |
| Unauthenticated admin APIs | Breaks security by default |
| Forking CMS logic into site repos | Breaks platform-first / extensibility |
| Client JS hacks for CMS API bugs | Hides complexity; hurts inspectability |
| Mixing presentation and logic in one file | Breaks UI decoupling |
| Duplicating docs outside Birkly-Docs | Breaks SSOT |

## Values check (required on every PR and daily digest)

```markdown
**Values check:** ✅ aligned | ⚠️ [value, trade-off, human approval needed]
```

Agents proceed when work clearly aligns. **Stop and ask** when a trade-off touches any value above.
