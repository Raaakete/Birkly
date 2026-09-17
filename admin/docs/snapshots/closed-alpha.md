# Closed alpha guide (product testing)

Reference for **future** closed-alpha self-host trials — not an open invite program. When your team runs a cohort, use this alongside [Self-host install](../07-operations/self-host-install.md) and [Known issues (Wave 1)](known-issues-wave1.md).

## Summary

Closed alpha means Birkly is mature enough for real self-hosted trials but **not** a public beta. Expect rough edges. Testers use the CMS on their own server, report bugs via in-admin **Feedback**, and read **Help** for live Birkly-Docs. Support: **hello@birkly.io**.

## What is in this alpha

| Area | What to test |
|------|----------------|
| **BYOAI** | Connect your provider (OpenAI, Claude, Ollama, …), assign a role, try chat read/write and approval queue |
| **MCP** | Optional: connect Claude Desktop with a per-connection MCP key |
| **Automation** | Enable the automation plugin; create a webhook or scheduled workflow |
| **User plugin** | Registration, login, collection permissions |
| **ContentEditor** | Block components (callout, quote, CTA, table, image) — save and reload entries |
| **Marketplace** | Install free official plugins; paid/subscription checkout on marketplace.birkly.io |
| **Commerce** | Multi-currency products, live import, warehouse stock, inventory webhooks |
| **Cloud account** | Optional link on Account page (local login still primary) |
| **Admin UX** | Light/dark/system theme toggle; mobile-friendly dashboard and Commerce |
| **Help** | Live docs from Birkly-Docs with offline snapshot fallback |
| **Feedback** | Submit bug/idea from the admin Feedback widget; confirm it appears in Feedback admin |

## Getting started

1. **Install** Birkly on a server or local PHP (see [Self-host install](../07-operations/self-host-install.md) and [Getting started](getting-started.md)).
2. Complete **onboarding** (project name, first admin user).
3. Open **Help** (sidebar) for contextual docs — **Read full doc** opens Birkly-Docs topics.
4. Install plugins from **Plugins → Hub** (Commerce, Events, Automation, User Management).
5. Connect **AI** under **Settings → AI** — start with Ollama locally if you have no API key.
6. Optionally **link cloud account** under Account (does not replace local password).

## Reporting issues

Use **Feedback** in the admin (not only email) so we capture:

- Your message and category (bug / idea / other)
- Optional screenshot note
- Page URL, CMS version, and browser info

We use this data only to improve the alpha (see [Alpha legal](alpha-legal.md) and website Privacy Policy). Include steps to reproduce for bugs.

## What not to expect yet

- Production SLA or 24/7 support
- Managed hosting from us — you self-host
- Full public marketplace without QC review for verified tier
- FX conversion between currencies (set each price explicitly)

## Useful doc links

- [Alpha legal](alpha-legal.md) · [Account](../01-admin/account.md) · [Admin appearance](../01-admin/admin-appearance.md)
- [AI connections](../01-admin/ai-connections.md) · [MCP connections](../01-admin/mcp-connections.md)
- [Marketplace go-live](../06-extensibility/marketplace-go-live.md)
- [Commerce multi-currency](../06-extensibility/commerce-multi-currency.md)
- [Automation overview](../05-automation/automation-overview.md)
- [User plugin](../06-extensibility/user-plugin.md)
- [ContentEditor blocks](../02-content/content-editor.md)
- [Plugin scaffolding CLI](../06-extensibility/scaffold-plugin-cli.md)

## Related

- [Admin language vs site language](../01-admin/admin-language-vs-site-language.md)
- [Getting started](getting-started.md)
- [Help and onboarding](../01-admin/help-and-onboarding.md)
- [Troubleshooting](../07-operations/troubleshooting.md)
- [Known issues (Wave 1)](known-issues-wave1.md)
