# P18 — NPM docs package go/no-go

**Date:** 2026-07-07 · **Phase:** P18-M7 · **Decision owner:** Keven (human)

## Question

Should Birkly product documentation be published as an `@birkly/docs` (or similar) npm package for consumers to install in their projects?

## Recommendation: **NO-GO** (defer)

Do **not** publish an npm docs package in P18. Keep documentation in **Birkly-Docs** (static site / GitHub) only.

## Rationale

| Criterion | Assessment |
|-----------|------------|
| **Project values** | Birkly core is dependency-free; no runtime should require npm to read CMS docs. |
| **Consumer need** | Template authors and site owners read docs in browser; CMS zip ships `START_HERE.html` and links to Birkly-Docs. |
| **Maintenance** | Dual publishing (site + npm) adds release coordination without a clear consumer. |
| **Security / supply chain** | Extra publish surface (npm token, typosquatting) for no alpha/beta requirement. |
| **i18n foundation (P18-M7-T1)** | Lang keys live in `lang/*.json`; admin strings are not npm-distributed today. |

## i18n foundation (companion scope)

**Lang key conventions (v1):**

- Files: `lang/en.json`, `lang/de.json`, … with `_meta` block per locale.
- Keys: dot-separated paths under `admin.*` for admin UI (`admin.plugins`, `admin.save`).
- JS: use `t('key', 'Fallback English')` via existing admin i18n helper.
- No full translation in P18 — English remains canonical; structure supports later locale PRs.

**Top admin screens audited for key coverage (sample):** dashboard, onboarding, plugins (installed + marketplace), settings (general, security, storage), collections list, entry editor, users/roles, automation, AI connections, feedback widget.

Gaps should be filled incrementally when touching a screen; no mass string extraction in P18.

## Revisit triggers

Re-evaluate npm docs publish when **all** are true:

1. A documented consumer needs offline docs inside `node_modules` (e.g. official Vite/Next starter depends on it).
2. Human approves npm org, package name, and release cadence.
3. CI can verify package contents match Birkly-Docs build artifact.

## If approved later

- Package: read-only markdown + manifest; **no** PHP/JS executable code.
- Version aligned with CMS semver tags.
- Publish from Birkly-Docs release workflow, not from CMS core repo.

## Sign-off

| Role | Decision | Date |
|------|----------|------|
| Product (Keven) | Defer — docs stay in Birkly-Docs | 2026-07-07 |
| Engineering | No publish pipeline in P18 | 2026-07-07 |
