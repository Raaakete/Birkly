# Admin language vs site language

## Summary

Birkly separates **two languages**:

| Layer | What it controls | Where you set it |
|-------|------------------|------------------|
| **Admin UI language** | Menus, buttons, onboarding, plugin admin shells | **Account** (and onboarding for the first user) |
| **Public site language** | Which localized **content** visitors see | Per-collection **multilanguage** fields, template `?lang=` / visitor picker — not a single General setting |

There is **no** site-wide language dropdown under **Settings → General**. Use **Account** for admin UI language.

## Beginner

1. **First login:** Onboarding asks for **admin UI language** — this saves to your user profile.
2. **Later:** Open **Account** → language → save → reload admin.
3. **Content:** Collections can use **multilanguage** fields separately; that is not the same as admin UI catalogs. See [Multilingual templating](../03-templating/multilingual-templating.md).

## Advanced

- Admin strings: `lang/*.json` in core and plugin `lang/` packs (P85 i18n).
- Legacy `settings/site.json` may still contain a `language` key for defaults; it is not exposed in General settings UI.

## Related

- [Account](account.md)
- [Settings](settings.md)
- [Getting started](../00-overview/getting-started.md)
