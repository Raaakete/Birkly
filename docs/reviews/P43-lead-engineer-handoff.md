# Phase 43 — Lead-engineer handoff (configurable hosting URLs)

**Status:** Approved (2026-07-25, Keven) — **execute P43-M1→M9**  
**Human rule:** Execute autonomously. Notify human **once** at **P43-M9** closure.

**Master plan:** `docs/reviews/P43-configurable-hosting-urls-plan.md`  
**Gap register:** `docs/reviews/P43-gap-register.md`  
**Matrix:** `docs/reviews/P43-verification-matrix.md`

**Baseline:** `main` with `BIRKLY_DATA` Docker volume, live `cms.birkly.cloud` + `birkly.cloud`, temporary `cms.` + `/project/` routing hacks.

---

## Copy-paste prompt for lead-engineer

```
You are the lead-engineer for Birkly Phase 43 — Configurable hosting URLs (CMS vs public site).

Human approved P43 (2026-07-25). Execute P43-M1→M9 autonomously.
Notify human ONCE at P43-M9 with verification matrix green + production smoke on birkly.cloud.

PROBLEM: Routing uses str_starts_with($host, 'cms.') and project/ folder presence — not settings.
Users can host public sites ANYWHERE; project/ is optional. Dokploy Internal Path /project was a harmful workaround.

LOCKED DECISIONS (P43-D1–D10):
- Settings-driven cms_base_url + public_site_url + mode (none|external|project)
- Remove cms. hostname magic from index.php
- /admin on non-CMS host → redirect to CMS URL
- public host: clean URLs (no /project/ in browser bar) when mode=project
- Auto-register public host in Domains allowlist on save
- Dokploy: Internal Path always / for all domains
- Migration infer cms.* or first login host when hosting.json empty

READ FIRST:
1. docs/reviews/P43-configurable-hosting-urls-plan.md
2. docs/reviews/P43-gap-register.md
3. docs/reviews/P43-verification-matrix.md
4. index.php (lines 63–78 — DELETE cms. hack)
5. core/site_domain.php, core/project_site.php, api/settings.php (admin.cms_url)
6. docs/deployment/docker.md
7. Birkly-Docs 07-operations/dokploy-hosting.md

═══ M1 — SETTINGS MODEL ═══

- settings/hosting.json + core/hosting_urls.php
- birkly_hosting_cms_host(), birkly_request_host_role(), birkly_hosting_migrate_legacy()
- Wire api/settings.php or api/hosting.php for CRUD
- Align admin.cms_url with cms_base_url

═══ M2 — ROUTING ═══

- index.php: role-based / handling
- CMS host / → admin (or same_host_landing)
- Public host / → public site root
- mode none: / → admin even if project/ exists
- Remove str_starts_with($host, 'cms.')

═══ M3 — ADMIN UI ═══

- Settings → Hosting tab (or Domains sub-section)
- CMS URL, public mode, public URL, same-host landing
- Generated birkly-client.js snippet
- Dokploy checklist (Internal Path /, volume /app/data, test URLs)
- Update Project tab to show public URL from hosting settings

═══ M4 — ONBOARDING ═══

- After first admin: confirm CMS URL, ask public site mode
- Skip allowed → mode none

═══ M5 — CLEAN URLS + NGINX ═══

- Public host: birkly.cloud/ not birkly.cloud/project/index.html
- docker/nginx-site.conf: map or try_files for project/ on public host
- Merge/supersede PR #289 nginx fixes if still open

═══ M6 — ADMIN CANONICAL HOST ═══

- birkly.cloud/admin → redirect cms.birkly.cloud/admin
- strict_admin_host setting (default true)

═══ M7 — CORS + HELPERS ═══

- site_domain.php uses hosting settings for CMS host
- birkly_public_site_url() returns clean URL on public host
- Auto domain register on save

═══ M8 — TESTS ═══

- tests/hosting_urls_test.php
- HTTP routing fixtures (simulate HTTP_HOST)
- ci-check.sh green

═══ M9 — DOCS + CLOSURE ═══

- docs/deployment/docker.md — settings model, not cms. convention
- Birkly-Docs PR: dokploy-hosting.md, deployment.md
- Fill P43-verification-matrix.md
- Production smoke: cms.birkly.cloud + birkly.cloud
- Single digest notification

DELEGATION:
cms-developer:     M1–M8
docs-writer:       M9 (Birkly-Docs sibling repo)
qa-verifier:       V-HOST matrix + production smoke at M9
design-reviewer:   M3 Hosting UI after implementation

DO NOT:
- Require cms. subdomain in docs or code
- Tell users to set Dokploy Internal Path to /project
- Start P41 design system or P42 marketing template in same wave

BRANCHES: cursor/p43-m[N]-<slug>-030a

CLOSURE:
[ ] cms. hack removed
[ ] V-HOST-01–25 green
[ ] birkly.cloud/ shows site without /project/ in URL (mode project)
[ ] cms.birkly.cloud/ → admin
[ ] Docs updated (Birkly + Birkly-Docs)
[ ] Human notified once
```

---

## Human approval checklist

- [x] P43-D1–D10 accepted (2026-07-25)
- [x] Next phase after Docker volume + birkly.cloud routing fix
- [x] P43-M0 plan PR merged
- [ ] P43-M1→M9 execution approved at closure review

---

## Milestone delegation summary

| Milestone | Primary | Repos |
|-----------|---------|-------|
| M1 | cms-developer | Birkly |
| M2 | cms-developer | Birkly |
| M3 | cms-developer | Birkly |
| M4 | cms-developer | Birkly |
| M5 | cms-developer | Birkly |
| M6 | cms-developer | Birkly |
| M7 | cms-developer | Birkly |
| M8 | cms-developer | Birkly |
| M9 | docs-writer + lead | Birkly, Birkly-Docs |

---

## Related PRs / context

- #282–#288 — `BIRKLY_DATA`, entrypoint, Dokploy docs
- #289 (if open) — nginx root 403 + `/project/` alias — fold into P43-M5

---

## Production reference

| URL | Role after P43 |
|-----|----------------|
| `https://cms.birkly.cloud` | CMS URL — `/` → admin |
| `https://birkly.cloud` | Public site URL — `/` → marketing root |

Dokploy: both domains, Path `/`, Internal Path `/`, port 80.
