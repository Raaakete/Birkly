# Multilingual templating

Show language-specific content and let visitors switch language with a simple `<select>` wired to the Birkly client.

## Summary

**In short:** The client resolves `{current.language}` from `?lang=`, then `localStorage` (`birkly_language`), then default `en`. Add `data-birkly-language-select` on a `<select>` to switch language (updates the query string, reloads, and remembers the choice). Use `{if current.language = 'de'}…{else}…{endif}` for small copy branches; store translated entry fields in the CMS for real content.

> **Video (follow-along):** coming — multi-step recording (language select → `?lang=` reload)

## Beginner

### Language switcher

```html
<select data-birkly-language-select>
  <option value="en">English</option>
  <option value="de">Deutsch</option>
  <option value="fr">Français</option>
</select>

<script src="/birkly-client.js"></script>
```

### Branch on language

```html
{if current.language = 'de'}
  <p>Willkommen!</p>
{else}
  <p>Welcome!</p>
{endif}
```

### Read the query

```html
<p>Lang param: {query.'lang'}</p>
<p>Active: {current.language}</p>
```

### Content strategy

- Prefer **translated fields / language-aware entries** in the CMS (admin languages) so loops return the right locale.  
- Use `{if current.language = …}` only for short chrome strings when you are not storing translations in entries.

## Advanced Users

### For developers & AI

**Resolution order (TEMPLATE_LANGUAGE_SPEC)**

1. `lang` URL query (`?lang=de`)  
2. `birkly_language` in `localStorage`  
3. Default `en`

**Switcher behaviour:** sets selected option, writes `?lang=…`, reloads, persists preference.

**Page bundle:** `site_elements` prefetch is language-aware (list for current language).

**Do not** invent `{t 'key'}` / Gettext-style helpers in public templates unless a plugin registers them — see [Plugin language extensions](plugin-language-extensions.md).

## Related

- [Conditionals](conditionals.md)
- [Variables and output](variables-and-output.md)
- [Client setup](client-setup.md)
- [Settings](../01-admin/settings.md)
