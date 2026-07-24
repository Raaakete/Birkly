# P36 — Verification matrix (CMS productization & platform depth)

**Status:** Draft — populate during P36-M0→M24  
**Plan:** `docs/reviews/P36-cms-productization-platform-plan.md`

---

## CMS release zip

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| ZIP-01 | No `demo-content/` in release zip | pending | |
| ZIP-02 | No `AGENTS.md` or agent scripts in zip | pending | |
| ZIP-03 | No plugin source files in zip | pending | |
| ZIP-04 | `content/.gitkeep` + `project/.gitkeep` present | pending | |
| ZIP-05 | `cms_release_manifest_test.php` green | pending | |
| ZIP-06 | Unzipped size below agreed budget | pending | |

---

## Ops starters

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| STR-01 | Ops admin: upload starter pack | pending | |
| STR-02 | GET /api/ops/v1/starters returns catalog | pending | |
| STR-03 | CMS onboarding installs blog starter from Ops | pending | |
| STR-04 | Files land in content/collections + project/ | pending | |
| STR-05 | Start blank works offline | pending | |

---

## Ops UI & features

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| OPS-01 | Sidebar has Marketplace / Platform / Marketing / System groups | pending | |
| OPS-02 | Featured offers API for website | pending | |
| OPS-03 | Installation detail page | pending | |
| OPS-04 | Revenue or webhook health widget on dashboard | pending | |

---

## Provider Studio

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| PROV-01 | Grouped provider sidebar | pending | |
| PROV-02 | Offer analytics page loads | pending | |
| PROV-03 | Draft offer save without publish | pending | |

---

## Documentation

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| DOC-01 | 06-plugin-economy/ section (8 pages) published | pending | |
| DOC-02 | No marketplace.birkly.io in new content | pending | |
| DOC-03 | Help manifest includes plugin economy entries | pending | |

---

## Regression

| ID | Check | Status | Evidence |
|----|-------|--------|----------|
| REG-01 | Marketplace tests green | pending | |
| REG-02 | Birkly ci-check.sh green | pending | |
| REG-03 | P35 surface isolation intact | pending | |

---

## Human review (M24)

| ID | Artifact | Status |
|----|----------|--------|
| HR-01 | P36 human review pack | pending |
| HR-02 | Clean CMS unzip screenshot | pending |
