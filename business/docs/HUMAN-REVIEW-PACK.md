# Human Review Pack — Business Materials v1

**Created:** 2026-07-13  
**Agent:** business-advisor  
**Status:** First complete draft — **pending human review**

---

## What was delivered

| Deliverable | Path | Status |
|-------------|------|--------|
| Business plan (13 sections) | `business/business-plan/` | ✅ Drafted |
| Pitch deck (13 slides) | `business/pitch-deck/slides.md` | ✅ Drafted |
| Investor one-pager | `business/investor/one-pager.md` | ✅ Drafted |
| Source of truth index | `business/docs/SOURCE_OF_TRUTH.md` | ✅ (pre-existing) |
| Agent handoff | `business/docs/BUSINESS-advisor-handoff.md` | ✅ (pre-existing) |

---

## Suggested review order

1. **[00-executive-summary.md](../business-plan/00-executive-summary.md)** — narrative accuracy
2. **[03-product.md](../business-plan/03-product.md)** — no overclaim on shipped features
3. **[08-traction-roadmap.md](../business-plan/08-traction-roadmap.md)** — stage honesty
4. **[pitch-deck/slides.md](../pitch-deck/slides.md)** — flow and timing (~10 min deck)
5. **[11-funding-ask.md](../business-plan/11-funding-ask.md)** + **[10-financials.md](../business-plan/10-financials.md)** — numbers
6. **[09-team.md](../business-plan/09-team.md)** — bios and photos
7. **[one-pager.md](../investor/one-pager.md)** — final distillation

---

## Every `[TBD]` needing human input

### Company & legal
- [ ] Legal entity name, registration, HQ
- [ ] Founded date
- [ ] Public URL and contact email
- [ ] Open-source license choice and GA announcement timing

### Team (Section 09, Slides 11)
- [ ] Founder full bio (2–3 paragraphs)
- [ ] Photo and LinkedIn
- [ ] Engineering team reality (agent-assisted vs FTE)
- [ ] Advisor names
- [ ] Hiring plan priorities

### Funding (Section 11, Slides 12)
- [ ] Round size (amount + currency)
- [ ] Instrument (SAFE, equity, grant mix)
- [ ] Valuation / cap
- [ ] Use of funds percentages
- [ ] Target close date
- [ ] Existing cap table summary

### Financials (Section 10)
- [ ] Marketplace take rate %
- [ ] Average plugin price assumptions
- [ ] Install growth scenarios (3-year model)
- [ ] Monthly burn and runway
- [ ] Revenue targets Y1–Y3

### Market & GTM (Sections 04, 06)
- [ ] Primary geography (DACH / EU / global)
- [ ] 5-year SOM ambition
- [ ] Agency design partner names (if citeable)
- [ ] Analytics / install telemetry approach (privacy)
- [ ] GitHub / open-source visibility strategy

### Traction claims
- [ ] Approve or remove marketing testimonial for investor use
- [ ] Any real install or waitlist numbers to include
- [ ] Approve external screenshots / demo videos

### Messaging
- [ ] Final tagline
- [ ] Confirm “free and open source” wording
- [ ] Investor-facing URL and calendar booking link

---

## Questions for Keven

1. **Funding:** What round size and timeline are you targeting? Pre-seed vs angel?
2. **Geography:** Is DACH/EU the primary GTM, or global English from day one?
3. **Open source:** When do you want to announce license and public repo?
4. **Metrics:** What waitlist or alpha user numbers (if any) can we cite?
5. **Team narrative:** How do you want to describe agent-assisted development to investors?
6. **Competitive sensitivity:** Any competitors to avoid naming in live pitches?
7. **Export format:** Do you want PDF/Keynote export next, or markdown iteration first?

---

## Approval checklist

- [ ] Fact-check pass against `SOURCE_OF_TRUTH.md`
- [ ] All critical TBDs resolved or consciously left blank
- [ ] Pitch deck rehearsed (~10 minutes)
- [ ] Legal review of forward-looking statements
- [ ] **Investor-ready sign-off** (Keven)

---

## After approval

1. Export deck to PDF/Keynote (human or tool)
2. Optional: create `Raaakete/Birkly-Business` repo and migrate `business/`
3. Add data room index under `business/investor/` if needed
4. Update handoff status in `BUSINESS-advisor-handoff.md`
