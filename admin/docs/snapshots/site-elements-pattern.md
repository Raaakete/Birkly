# Site elements pattern

## Summary

**Site elements** is a content-model pattern for shared site chrome: headers, footers, announcement bars, and other blocks that appear on many pages. You store them as **entries** in a dedicated collection (typically handle `site-elements`) and inject them on your site with **`<birkly-region>`** — not as a separate CMS content type.

## Beginner

### Why a collection, not a “global settings” doc?

Birkly uses **collections + entries only**. Shared layout content is still entries — usually one entry per region (`header`, `footer`). That keeps versioning, drafts, multilanguage, and the Public API consistent with the rest of your content.

### Setup steps

1. **Create collection** — Name: “Site elements”, handle: `site-elements`.
2. **Fields (example):**
   - **Title** (text) — admin label, e.g. “Header”.
   - **Blocks** (Dynamic items) — logo, nav links, CTA buttons, legal links.
3. **Create entries** — one per region:
   - Entry slug `header` — main site header blocks.
   - Entry slug `footer` — footer columns and copyright.
4. **Publish** entries when ready (status **Published**).
5. **On your site**, bind regions:

```html
<birkly-region collection="site-elements" entry="header" field="blocks"></birkly-region>

<main><!-- page-specific content --></main>

<birkly-region collection="site-elements" entry="footer" field="blocks"></birkly-region>
```

The Birkly client fetches live content and hydrates the region. See [Web components](../04-integration/web-components.md) and [Choose your stack](../04-integration/choose-your-stack.md).

### Multilingual sites

Enable **multilanguage** on the site-elements collection. Edit **Blocks** per language tab so header/footer copy matches each locale. Regions respect the active site language when configured.

### When not to use site elements

| Scenario | Better pattern |
|----------|----------------|
| Many similar items (blog posts, products) | Regular collection + list/detail templates |
| Child rows on one page (comments) | [Sub-collections](sub-collections.md) |
| One long article | Single entry with Content editor field |
| Visitor-submitted data | Public API collection — [Public forms](../04-integration/public-forms.md) |

## Advanced Users

**Handles in templates and API:**

- Collection: `{from 'site-elements' get entries}` or Public API `GET /api/public/collections/site-elements/entries/header`
- Prefer stable entry slugs (`header`, `footer`) — referenced in HTML and build scripts.

**Dynamic items inside regions:** The `field` attribute points to your Dynamic items field API name (e.g. `blocks`). Block structure is defined in [Dynamic items field](dynamic-items-field.md).

**Draft workflow:** Keep region entries as **Draft** while redesigning; published site keeps the last published version until you publish updates.

## Related

- [Content model overview](content-model-overview.md)
- [Dynamic items field](dynamic-items-field.md)
- [Collections](collections.md)
- [Entries](entries.md)
- [Web components](../04-integration/web-components.md)
- [Reference projects](../03-templating/reference-projects.md)
