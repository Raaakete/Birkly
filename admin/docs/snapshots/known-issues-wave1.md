# Known issues — Wave 1 engineering bar

## Summary

Public list of **accepted limitations** while P86 (self-host–ready engineering) is in progress. This is **not** a promise that a closed-alpha cohort is open — see product announcements separately.

**Support:** hello@birkly.io · in-admin **Feedback** (optional contact consent).

## P1 — documented limitations

| Area | Issue | Workaround |
|------|--------|------------|
| **Hub (alpha)** | **Events** and **Studio** show as *Coming soon* — not installable in Wave 1 | Use **User**, **Commerce**, and **Automation** from the Hub |
| **Architecture (P74)** | User ↔ Commerce club/membership boundaries still migrating to shelf model | Use documented plugin flows; report cross-plugin bugs via Feedback |
| **Hub** | Plugin versions must be installed/upgraded via Marketplace; pin versions your team documents | Hub → Update per plugin |
| **Email** | User activation and some notifications need **Connections → SMTP** | Run test email before signup tests |
| **i18n** | **German (`de`)** is the strict quality bar; other admin locales may show English fallbacks | Prefer `de` or `en` for demos |

## P2 — deferrals

| Area | Issue |
|------|--------|
| **Locales** | da, es, fr, hi, ja, zh admin catalogs incomplete vs `en` |
| **Commerce** | FX conversion between currencies — set prices per currency explicitly |
| **Feedback** | Self-host stores JSON under `data/feedback/` unless optional Ops relay is configured |
| **Journeys** | Full P28 beta journey catalog not required for engineering closure |

## Fixed recently (reference)

- OSS git history scrub for sensitive paths (public `Raaakete/Birkly`)
- Feedback relay default on Birkly-operated installs; consent + legal Help topic
- Onboarding admin language + Settings site language decoupling
- User plugin delete/permissions (Hub User Management 1.0.97+)

## Related

- [Self-host install](../07-operations/self-host-install.md)
- [Closed alpha guide](closed-alpha-guide.md) (product testing notes)
- [Alpha legal](alpha-legal.md)
