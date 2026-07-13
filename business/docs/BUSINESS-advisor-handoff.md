# Business advisor — handoff (business plan + pitch deck)

**Status:** Approved — execute now (2026-07-13)  
**Assignment:** **Start from scratch** — replace any bad prior drafts; archive obsolete content to `business/archive/2026-07-13/`  
**Human rule:** Draft for human review. Notify when **first complete draft** is ready (business plan + pitch deck + one-pager). Do not claim investor-ready until human approves.

**Workspace:** `business/` in **`Raaakete/Birkly`** (repo root, branch `main`)  
**Agent:** `.cursor/agents/business-advisor.md`  
**Facts:** `business/docs/SOURCE_OF_TRUTH.md`

> **Cloud agents:** Materials landed on `main` via PR #166 (2026-07-13). Start a **new** session on the Birkly environment so the VM clones latest `main`. If the advisor says files are missing, the session predates the merge.

---

## Copy-paste prompt — refine v1 (after you supply TBD data)

```
You are the business-advisor for Birkly.

VERIFY FIRST — in Raaakete/Birkly repo root on main:
  ls business/docs/
  ls business/business-plan/
  ls business/pitch-deck/
Required: HUMAN-REVIEW-PACK.md, SOURCE_OF_TRUTH.md, slides.md, 00-executive-summary.md through 12-risks.md.
If missing: stop — human must use latest main (PR #166 merged) and a new cloud session.

ASSIGNMENT: Refine the v1 business plan and pitch deck. Resolve [TBD] items using HUMAN DATA below.

READ:
1. business/docs/HUMAN-REVIEW-PACK.md
2. business/docs/SOURCE_OF_TRUTH.md
3. business/business-plan/ (all sections)
4. business/pitch-deck/slides.md
5. business/investor/one-pager.md

HUMAN DATA (fill in before running — or mark remaining TBDs):
- Funding: [amount, instrument, use of funds %]
- Team: [founder bio, photo ref, advisors]
- Financials: [take rate %, Y1–Y3 install/revenue assumptions]
- Market: [primary geography, 5-year SOM target]
- Traction: [waitlist/install numbers if citeable]
- Contact: [email, URL, calendar link]
- Open source: [license + GA timing]

DELIVERABLES:
- Update all business-plan sections + executive summary for consistency
- Update pitch-deck/slides.md and investor/one-pager.md
- Refresh HUMAN-REVIEW-PACK.md (remaining TBDs only)
- Do NOT invent metrics — use human data or keep [TBD]

Notify when investor-ready draft is complete for human sign-off.
```

---

## Copy-paste prompt — initial draft (complete)

```
You are the business-advisor for Birkly.

ASSIGNMENT: Create a COMPLETE business plan and pitch deck FROM SCRATCH.
The prior solution was bad — do not reuse without rewriting. Archive anything you replace.

READ FIRST:
1. business/docs/BUSINESS-advisor-handoff.md (this file)
2. business/docs/SOURCE_OF_TRUTH.md
3. .cursor/agents/business-advisor.md
4. docs/PROJECT_VALUES.md
5. docs/roadmap/ROADMAP.md (skim current state — P25–P28)
6. README.md
7. Birkly-Marketplace/AGENTS.md (sibling repo)
8. Birkly-Website/CONTENT_GUIDE.md (sibling repo)

═══ DELIVERABLE 1 — COMPLETE BUSINESS PLAN ═══

Write one markdown file per section under business/business-plan/:

  00-executive-summary.md   (write LAST — synthesize all sections)
  01-problem.md
  02-solution.md
  03-product.md             (CMS + marketplace + plugins — factual)
  04-market.md              (TAM/SAM/SOM with assumptions stated)
  05-business-model.md      (marketplace take rate, hosting, services — mark TBDs)
  06-go-to-market.md        (closed alpha → beta → waitlist)
  07-competition.md         (WordPress, Strapi, Contentful, etc. — honest diff)
  08-traction-roadmap.md    (shipped phases P16–P28; beta launch P28)
  09-team.md                [TBD: human bios]
  10-financials.md          [TBD: human projections]
  11-funding-ask.md         [TBD: amount, use of funds — structure only]
  12-risks.md
  README.md                 (index + how to read)

Rules:
- Every product claim traceable to SOURCE_OF_TRUTH
- Use [TBD: …] for numbers you don't have
- Tone: confident, precise, not hype
- Stage: pre-/early beta, invite-only

═══ DELIVERABLE 2 — PITCH DECK ═══

Write business/pitch-deck/slides.md — 12–15 slides:

  Slide 1:  Title + tagline
  Slide 2:  Problem
  Slide 3:  Solution
  Slide 4:  Product (platform + marketplace)
  Slide 5:  Why now
  Slide 6:  Market
  Slide 7:  Business model
  Slide 8:  Traction / roadmap (real shipped milestones)
  Slide 9:  Go-to-market
  Slide 10: Competition / differentiation
  Slide 11: Team [TBD]
  Slide 12: The ask [TBD]
  Slide 13: Contact / next steps

Each slide: headline, 3–5 bullets max, optional **Notes:** for speaker.

Must match business plan narrative (same positioning, no contradictions).

═══ DELIVERABLE 3 — INVESTOR ONE-PAGER ═══

business/investor/one-pager.md — single-page distillation (problem, solution, market, ask, contact).

═══ DELIVERABLE 4 — HUMAN REVIEW PACK ═══

business/docs/HUMAN-REVIEW-PACK.md:
- Links to all sections
- List of every [TBD] needing human input
- Suggested review order
- Questions for Keven (funding amount, team, financials, traction claims)

═══ ARCHIVE ═══

If you find bad existing business content, move to business/archive/2026-07-13/ with a short README explaining why it was retired.

═══ DO NOT ═══

- Write CMS or product code
- Invent revenue, user counts, or customer logos
- Change docs/roadmap/ROADMAP.md engineering scope
- Export PDF unless human asks (markdown is v1)

═══ COMPLETION ═══

Notify human when first full draft is ready for review:
[ ] All business-plan sections drafted
[ ] pitch-deck/slides.md complete
[ ] investor/one-pager.md complete
[ ] HUMAN-REVIEW-PACK.md with TBD list
```

---

## Human approval checklist

- [x] Start from scratch approved (2026-07-13)
- [x] Complete business plan + pitch deck scope approved
- [x] Human review of first draft (2026-07-13 — business-advisor v1 delivered)
- [ ] Investor-ready sign-off (after Keven resolves TBDs in HUMAN-REVIEW-PACK.md)
