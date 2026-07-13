# P28 — Real test projects & video review policy

**Date:** 2026-07-13 · **Human requirement:** See every journey solution on video; agents install **real** projects, not mocks.

---

## Real projects — not fakes

Journeys use **real CMS instances and real content**, the same way a human would set them up:

| Journey | What gets built (real) |
|---------|-------------------------|
| **J-INSTALL** | Extract **`build-cms-release.sh` zip** to a temp directory; real `php -S`; real first user in `users/`; real onboarding state in `settings/` |
| **J-BLOG … J-INFLUENCER** | `./scripts/install-reference-project.sh <name>` — copies **real collections** into `content/collections/`; serves **real HTML** from `reference-projects/<name>/website/` |
| **J-COFFEE / J-YOGA** | **Real plugin install** (commerce / events) via marketplace client or `install-dev-plugins.sh`; real plugin routes active |
| **J-MARKETPLACE** | **Real plugin ZIP** from registry fixture downloaded and extracted to `plugins/` — not a stub that skips install |
| **J-ADMIN** | Real admin session; real entry created in flat files; real publish via API |
| **J-INVITE** | Real invitation token in storage; real `invite.php` flow |
| **J-KEKABA** | Real kekaba repo after **`migrate-kekaba-live-path.sh`**; real `Birkly-0.1/` embed sync; **no** `birkly-baked` on primary pages |

**HTTP assertions** (curl/DOM) prove correctness in CI. **Videos** prove the same flows visually for human review.

**Not allowed:** Mocking journey steps with hardcoded “pass” without disk state; testing against empty HTML stubs; skipping plugin file install.

---

## Video evidence (required at P28-M17)

Each major journey has a **screen recording** + **step-by-step evidence doc** (same pattern as V-STU-01–V-STU-04).

| Video ID | Journey | Shows |
|----------|---------|--------|
| **V-BETA-01** | J-INSTALL | Extract zip → START_HERE → wizard → dashboard |
| **V-BETA-02** | J-BLOG | Blog reference site in browser — home, list, post |
| **V-BETA-03** | J-COFFEE | Coffee shop — browse, variant, cart |
| **V-BETA-04** | J-YOGA | Events — upcoming, schedule, event detail |
| **V-BETA-05** | J-MARKETPLACE | Admin installs commerce from marketplace UI |
| **V-BETA-06** | J-ADMIN | Create + publish entry; visible on public site |
| **V-BETA-07** | J-INVITE | Invite email/link → accept → login |
| **V-BETA-08** | J-KEKABA | kekaba live path — no bake; real content renders |
| **V-BETA-09** | Ops | Operations tab + scheduled smoke status |

**Artifacts:** `docs/reviews/artifacts/v-beta-*.md` + recordings under `/opt/cursor/artifacts/` (referenced in closure digest).

**Who records:** `qa-verifier` + `computerUse` subagent after automated journeys pass.

---

## Human review pack (P28-M18)

Delivered **once** at closure so you can review everything yourself:

1. **`docs/reviews/P28-human-review-pack.md`** — index of all videos with links, duration, and **replay commands** (copy-paste to reproduce locally)
2. **Matrix green** — `P28-verification-matrix.md`
3. **Closure checklist** — `P28-phase-closure-checklist.md`
4. **Single digest** — links to every V-BETA video

You watch videos → replay any journey locally with one command → sign off beta readiness.

---

## Replay commands (examples)

```bash
# Blog journey — real reference project
./scripts/install-reference-project.sh blog
php -S localhost:8080 -t . server-router.php
# Open http://localhost:8080/reference-projects/blog/website/index.html

# All journeys (automated)
./scripts/journey-runner.php --all

# kekaba live path (sibling repo)
cd ../kekaba && ./scripts/migrate-kekaba-live-path.sh
./scripts/journey-runner.php --journey J-KEKABA --base http://localhost:8000
```
