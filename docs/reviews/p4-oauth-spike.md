# P4-M8 — OAuth / device-flow discovery spike

**Date:** 2026-06-26  
**Status:** Discovery complete — **no-go for Phase 4 production code**  
**Owner:** Lead engineer + cms-developer research

## Executive summary

| Question | Answer |
|----------|--------|
| Ship consumer OAuth in P4? | **No** — documentation and go/no-go only |
| Replace API-key wizard with OAuth login? | **No** in P4; remain API-key wizard |
| Recommended path for non-technical users? | **Inbound MCP** (Claude Desktop, emerging clients) + in-admin chat with Ollama |
| If OAuth is desired later? | Propose **Phase 5** milestone after separate human approval |

## Problem statement

Alpha testers sometimes confuse **consumer subscriptions** (ChatGPT Plus, Claude Pro) with **developer API access**. OAuth-style “Sign in with OpenAI/Anthropic” could reduce friction for outbound BYOAI, but it introduces security, compliance, and maintenance cost on a self-hosted CMS.

## API key vs consumer subscription

| | Provider API key | Consumer subscription |
|--|------------------|----------------------|
| **What it is** | Developer credential from provider dashboard | Monthly app subscription for chat UI |
| **Works with Birkly outbound chat?** | **Yes** | **No** |
| **Billing** | Pay-per-token / usage tier | Fixed monthly fee |
| **Examples** | `sk-…` from platform.openai.com; Anthropic console key | ChatGPT Plus, Claude Pro, Gemini Advanced |
| **Birkly UI copy** | Wizard asks for API key; links to provider docs | Explicit callout: subscription ≠ API |

**Decision:** Keep API-key wizard. Add inline help and validation errors (P4-M7) — do not imply login replaces keys.

## Provider OAuth landscape

| Provider | OAuth for API access? | Notes |
|----------|----------------------|-------|
| OpenAI | No public OAuth for arbitrary third-party API proxy | API keys only for server-side chat |
| Anthropic | No consumer OAuth for CMS-style API proxy | API keys from console |
| Google (Gemini) | API keys; limited OAuth for specific Google Cloud flows | Overkill for typical self-hosted install |
| OpenRouter | API key | Aggregator; no end-user OAuth suitable for Birkly |

None of the primary BYOAI providers offer a drop-in “authorize Birkly to call the API on my behalf” flow comparable to “Sign in with Google” for identity-only apps.

## Device authorization flow (RFC 8628)

**Evaluated:** Optional device-code flow for headless or CLI setup (display user code, poll token endpoint).

| Pros | Cons |
|------|------|
| Familiar pattern for TV/CLI apps | Still requires provider support — **not available** for OpenAI/Anthropic API |
| Avoids pasting long keys on mobile | Adds token storage, refresh, revocation UX in Birkly |
| | New attack surface on self-hosted instances |

**Verdict:** Not viable for outbound BYOAI without provider partnerships. **Do not implement in P4.**

## Security considerations

- OAuth tokens on disk in `settings/` increase blast radius vs per-connection encrypted API keys.
- Self-hosted instances lack centralized secret rotation; refresh-token leaks are operator burden.
- MCP inbound already gives non-technical users a path **without** storing provider keys on Birkly.

## Recommendation by user type

| User | Recommended path |
|------|------------------|
| Non-technical editor | Claude Desktop + **MCP Connections** (inbound); or Ollama locally |
| Technical admin | Outbound BYOAI with API key; approval queue on |
| Automation | Webhooks / automation plugin — not OAuth |

**MCP for non-technical users:** External assistant connects with one Birkly-issued MCP key; no provider API key on the server. Role and approval still apply.

## Go / no-go table

| Option | Go? | Phase |
|--------|-----|-------|
| API-key wizard (status quo, improved copy) | **Go** | P4 (M7) |
| Inbound MCP standalone endpoints | **Go** | P4 (M3–M6) |
| Consumer OAuth for outbound providers | **No-go** | — |
| Device-code flow without provider API | **No-go** | — |
| Revisit OAuth if provider ships CMS-friendly OAuth | **Conditional go** | P5+ with human approval |

## P4 deliverables (this spike)

- [x] Written go/no-go (`docs/reviews/p4-oauth-spike.md`)
- [x] UI remains API-key wizard on no-go
- [x] **No OAuth production code in P4**

## If go in Phase 5+ (outline only)

1. Human approves new roadmap milestone (not an extension of P4).
2. Threat model: token storage, CSRF on callback, per-instance redirect URIs.
3. Provider-specific adapters behind `core/` interface; no npm dependency in core.
4. Migration path from API keys; opt-in only.

## References

- `docs/ai/BYOAI-ARCHITECTURE.md` — two-channel model
- `docs/roadmap/ROADMAP.md` — P4-M8 tasks
- `docs/reviews/P4-lead-engineer-handoff.md` — locked decisions
