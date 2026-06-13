# Birkly Platform Roadmap

## Plan governance

Human and product-planner agent share ownership of this file.

| Change type | Who decides | Planner action |
|-------------|-------------|----------------|
| **Initial roadmap** | Human + planner together | Ask questions → draft in Plan mode → **wait for approval** → then PR |
| **Small updates** | Planner autonomously | Edit directly; PR when batching status or clarity updates |
| **Major updates** | Human must approve first | Propose change → **wait for approval** → then edit / PR |

**Small updates include:** task status, wording, subtasks under existing milestones, path/owner fixes, notes/blockers.

**Major updates include:** new/removed milestones, vision changes, new cross-repo epics, milestone reprioritization, significant scope or timeline shifts.

When in doubt, treat as major and ask the human.

### Approved baseline

_Not set yet — first product-planner session will establish this after human approval._

## Vision

Build a reliable, zero-dependency flat-file CMS (Birkly) and maintain two reference client sites (Birkly Website, Kekaba) plus synced documentation — all driven by a cloud agent team with clear milestones and acceptance criteria.

## Current state

| Repo | Status |
|------|--------|
| Birkly (CMS) | Active development, GitHub connected |
| Birkly-Website | Local work ahead of remote; reconnecting to GitHub |
| kekaba | New empty repo; site code being pushed |
| Birkly-Docs | Active, separate repo |

## Milestones

### Milestone 1: Agent infrastructure

- **Status**: in progress
- **Repos**: Birkly
- **Goal**: Cloud agents can boot, read roadmap, and delegate work

**Tasks**

- [x] Add `AGENTS.md` and `.cursor/agents/` team roles to Birkly
- [x] Add `.cursor/environment.json` for cloud VM bootstrap
- [ ] Configure multi-repo cloud environment (Birkly, Birkly-Website, kekaba, Birkly-Docs)
- [ ] Run product-planner agent to refine this roadmap via PR
- [ ] Run lead-engineer agent on Milestone 2

**Acceptance criteria**

- All four repos have at least one commit on GitHub
- Cloud environment snapshot passes a smoke test (PHP server starts, repos visible)
- Team config merged to Birkly `main`

---

### Milestone 2: Repo hygiene and CMS sync

- **Status**: not started
- **Repos**: Birkly, Birkly-Website, kekaba
- **Goal**: Each client site has a clean git history; embedded `Birkly-0.1/` copies are documented

**Tasks**

- [ ] Document CMS vendoring policy: when and how to sync `Birkly-0.1/` from Birkly releases
- [ ] Ensure `.gitignore` excludes runtime data in all site repos
- [ ] Align Birkly-Website local state with GitHub remote
- [ ] Push kekaba site structure (HTML, components, CMS embed)

**Acceptance criteria**

- No runtime secrets or user data in any repo
- README in each site repo explains local dev and CMS embed
- Lead agent can clone all repos and start dev servers

---

### Milestone 3: Birkly Website beta polish

- **Status**: not started
- **Repos**: Birkly-Website, Birkly-Docs
- **Goal**: Marketing site reflects current beta features; docs stay in sync

**Tasks**

- [ ] Audit pages: index, blog, roadmap, docs, sponsors
- [ ] Reconcile docs approach (submodule vs embedded docs folder)
- [ ] Fix broken links and stale content
- [ ] QA pass on mobile and accessibility

**Acceptance criteria**

- All main pages load over HTTP with no console errors
- Docs page renders content correctly
- design-reviewer and qa-verifier sign off

---

### Milestone 4: Kekaba site launch prep

- **Status**: not started
- **Repos**: kekaba, Birkly
- **Goal**: Kekaba site ready for content fill and deploy

**Tasks**

- [ ] Push full site template and components to kekaba repo
- [ ] Configure CMS collections for kekaba content model
- [ ] Set up publish/bake pipeline (if not already)
- [ ] Deploy smoke test on staging

**Acceptance criteria**

- Content editors can add blog posts and pages via Birkly admin
- Static publish output is generated correctly
- No hardcoded production secrets in repo

---

## Task handoff format

When the product agent adds tasks, use this structure:

```markdown
### Task: [title]
- **Owner**: cms-developer | frontend-developer | docs-writer | qa-verifier | design-reviewer
- **Repo(s)**: Birkly | Birkly-Website | kekaba | Birkly-Docs
- **Files**: path/to/files
- **Depends on**: [task id or "none"]
- **Acceptance criteria**:
  - ...
```
