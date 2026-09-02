# Site elements pattern

## Summary

**Site elements** is a content-model pattern for shared site chrome: headers, footers, announcement bars, and other blocks that appear on many pages. You store them as **entries** in a dedicated collection and inject them on your site with **`<birkly-region>`** — not as a separate CMS content type.

The client prefetches a **`site_elements`** bundle key for page loads. The CMS slugifies collection handles to **lowercase hyphen** form (e.g. name “Site Elements” → slug **`site-elements`**). Public read and `page_bundle` treat **`site_elements` and `site-elements` as the same collection** via slug normalization — templates may keep the underscore alias.

## Beginner

### Why a collection, not a “global settings” doc?

Birkly uses **collections + entries only**. Shared layout content is still entries — usually one entry per region (`header`, `footer`). That keeps versioning, drafts, multilanguage, and the Public API consistent with the rest of your content.

### Setup steps

1. **Create collection** — Name: “Site elements”. Either set slug explicitly to **`site-elements`**, or accept the auto slug from the title (also `site-elements`). Do not rely on a literal underscore slug from the name alone — `create_collection` normalizes underscores to hyphens.
2. **Fields (example):**
   - **Title** (text) — admin label, e.g. “Header”.
   - **Blocks** (Dynamic items) — logo, nav links, CTA buttons, legal links — *or* a simple content field for HTML chrome.
3. **Create entries** — one per region:
   - Entry slug `header` — main site header blocks.
   - Entry slug `footer` — footer columns and copyright.
4. **Publish** entries when ready (status **Published**).
5. **On your site**, bind regions:

```html
<birkly-region collection="site_elements" entry="header"></birkly-region>

<main><!-- page-specific content --></main>

<birkly-region collection="site_elements" entry="footer"></birkly-region>
```

Or with a Dynamic items field named `blocks`:

```html
<birkly-region collection="site_elements" entry="header" field="blocks"></birkly-region>
```

The Birkly client fetches live content and hydrates the region. See [Web components (templating)](../03-templating/web-components.md) and [Choose your stack](../04-integration/choose-your-stack.md).

### Multilingual sites

Enable **multilanguage** on the `site_elements` collection. Edit **Blocks** (or content) per language tab so header/footer copy matches each locale. Regions respect the active site language when configured.

### When not to use site elements

| Scenario | Better pattern |
|----------|----------------|
| Many similar items (blog posts, products) | Regular collection + list/detail templates |
| Child rows on one page (comments) | [Sub-collections](sub-collections.md) |
| One long article | Single entry with Content editor field |
| Visitor-submitted data | Public API collection — [Public forms](../04-integration/public-forms.md) |

## Advanced Users

**Handles in templates and API:**

- Prefer `{from 'site_elements' get entry 'header'}…{endget}` (underscore alias) or `{from 'site-elements' get entry 'header'}…{endget}` — both resolve to the same collection when slugified handles match.
- `<birkly-region collection="site-elements" …>` should use the **stored collection slug** from admin/API. The client bundle still requests the `site_elements` alias; public read maps it to your on-disk collection.
- If you use a completely different handle, templates and regions must use **that** slug consistently; engine defaults and prefetch still special-case the `site_elements` alias.

**Dynamic items inside regions:** The `field` attribute points to your Dynamic items field API name (e.g. `blocks`). Block structure is defined in [Dynamic items field](dynamic-items-field.md).

**Draft workflow:** Keep region entries as **Draft** while redesigning; published site keeps the last published version until you publish updates.

## Related

- [Content model overview](content-model-overview.md)
- [Dynamic items field](dynamic-items-field.md)
- [Collections](collections.md)
- [Entries](entries.md)
- [Web components (templating)](../03-templating/web-components.md)
- [Reference projects](../03-templating/reference-projects.md)
