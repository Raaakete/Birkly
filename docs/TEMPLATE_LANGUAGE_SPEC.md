## Birkly Template Language Spec v1.0 (P45)

**Status:** Template Language Spec **v1.0** (P45)  
**Scope:** Public Light DOM client (`birkly-client.js`) — the syntax used by project sites and demos.

This is the canonical reference for the plain-English template language. AI packs and Birkly-Docs should match this file.

### Table of contents

1. [Client script](#client-script)
2. [Core ideas](#core-ideas)
3. [Light DOM vs Shadow DOM](#light-dom-vs-shadow-dom)
4. [Regions — `<birkly-region>` and `data-birkly-region`](#regions--birkly-region-and-data-birkly-region)
5. [Nested regions](#nested-regions)
6. [`Birkly.processTemplates()`](#birklyprocesstemplates)
7. [Events](#events)
8. [`site_elements` prefetch](#site_elements-prefetch)
9. [Output placeholders](#output-placeholders)
10. [Loops](#loops)
11. [Ordering (G2-04)](#ordering-g2-04)
12. [Existence and field checks](#existence-and-field-checks)
13. [From … get … (single entry)](#from--get--single-entry)
14. [System variables and context](#system-variables-and-context)
15. [Current entry and URL context](#current-entry-and-url-context)
16. [Enhance-only components (`birkly-components.js`)](#enhance-only-components-birkly-componentsjs)
17. [Plugin extension model](#plugin-extension-model)
18. [Not implemented / phantoms](#not-implemented--phantoms)
19. [Open design questions](#open-design-questions)

---

### Client script

Static HTML pages load the Birkly client from your CMS host:

```html
<script src="/birkly-client.js" data-cms-url="https://your-cms-domain.com"></script>
```

When the page is served from the same host as the CMS (e.g. `project/` preview), `/birkly-client.js` alone is enough. External sites must set `data-cms-url` to the CMS base URL.

Optional enhance-only helpers (nav active state, etc.):

```html
<script src="/birkly-components.js"></script>
```

---

### Core ideas

- **Plain English**: Templates are written as readable sentences inside `{…}` delimiters.
- **Collections** are always written in **single quotes**: `'blog'`, `'comments'`, `'services'`.
- **Entries** are the items inside a collection and are normally referred to as `entry` inside loops and `{from … get entry …}` blocks.
- **Fields**:
  - System fields like `title` don’t use quotes.
  - User-defined fields are referenced in quotes: `{'content'}`, `{'price'}`.
- **Plugins** can extend the language with extra sentences/keywords, but the **core language is loaded last** so core keywords win on conflicts.

---

### Light DOM vs Shadow DOM

| Surface | Template language (`{for each}`, `{from …}`, regions) | How to render CMS content |
|---------|------------------------------------------------------|---------------------------|
| **Light DOM** (normal page HTML) | Yes — scanned and processed by `Birkly.processTemplates()` | Write templates in markup, or use `<birkly-region>` |
| **Shadow DOM** (closed/open roots) | **No** — Shadow trees are **not** scanned for template syntax | Fetch the public API, then call `Birkly.render(html, data)` on a string |

Policy (P45-D6):

- **Light DOM = template language** — region discovery, page bundle, auto-init.
- **Shadow DOM = fetch API + `Birkly.render(html, data)` only** — do not expect `{for each}` inside a shadow root to run automatically.

`Birkly.render(html, data)` is the string engine used by both paths. Scoped Light DOM processing goes through `Birkly.processTemplates({ root })`, which discovers regions and loads data before calling the engine.

---

### Regions — `<birkly-region>` and `data-birkly-region`

Regions are Light DOM roots that Birkly discovers and renders. Three ways to mark them:

1. Markup that contains template syntax (`{for each…}`, `{from…}`, etc.)
2. Explicit attribute: `data-birkly-region`
3. Custom element: `<birkly-region>`

#### One-line `<birkly-region>` (entry)

```html
<birkly-region collection="site_elements" entry="header"></birkly-region>
```

Fetches that entry and renders a default template (see defaults below). Equivalent to a `{from … get entry …}{endget}` block without repeating it on every page.

#### One-line `<birkly-region>` (list)

```html
<birkly-region collection="blog" list ordered="order"></birkly-region>
```

- `list` — render all entries in the collection (default item: `<article class="birkly-region-item"><h2>{title}</h2></article>`).
- `ordered="…"` — optional; passed through as `ordered by …` on the generated loop (see [Ordering](#ordering-g2-04)).

#### Child HTML overrides the default

Any child HTML becomes the template body. If children lack `{from}` / `{for}`, Birkly wraps them using the `collection` / `entry` / `list` attributes:

```html
<birkly-region collection="site_elements" entry="header">
  <header class="site-header">{'content'}</header>
</birkly-region>
```

```html
<birkly-region collection="blog" list ordered="newest">
  <article><h2>{title}</h2><p>{'excerpt'}</p></article>
</birkly-region>
```

If children already include `{from` or `{for`, they are used as-is.

#### Default templates (`site_elements`)

Built-in defaults exist for:

| Collection + entry | Default wrapper |
|--------------------|-----------------|
| `site_elements` / `header` | `<header class="birkly-site-header">{'content'}</header>` |
| `site_elements` / `footer` | `<footer class="birkly-site-footer">{'content'}</footer>` |
| `site_elements` / `navigation` | `<nav class="birkly-site-nav">{'content'}</nav>` |

Other `collection` + `entry` pairs fall back to `{title}{'content'}` inside a `{from … get entry …}` block.

#### `data-birkly-region` (implemented)

Any element may declare itself an explicit region root:

```html
<div data-birkly-region collection="blog" list ordered="order">
  <a href="{slug}">{title}</a>
</div>
```

- **Implemented** in `birkly-client.js` (P45-M4): discovery, late-mount `MutationObserver`, and prepare/wrap when `collection` / `list` attributes are present.
- `<birkly-region>` hosts are also marked with `data-birkly-region` when prepared.
- Processed regions get `data-birkly-processed="1"` (skipped on later passes unless `force: true`).

---

### Nested regions

Discovery prefers **outermost** Light DOM candidates that contain template syntax.

**Exception:** an **explicit** region — `data-birkly-region` or `<birkly-region>` — **demotes** any ancestor that would otherwise be the region. The explicit child becomes the region root; the ancestor is not processed as a separate region.

| Situation | Winner |
|-----------|--------|
| Nested template-bearing elements, no explicit attribute | Outermost |
| Child has `data-birkly-region` or is `<birkly-region>` | Explicit child (ancestor demoted) |
| Candidate inside an explicit region | Not a separate region |

---

### `Birkly.processTemplates()`

Scoped re-render API (full document when called with no args / no `root`):

```js
await Birkly.processTemplates();
await Birkly.processTemplates({ root: el, silent: true, force: false });
```

| Option | Type | Meaning |
|--------|------|---------|
| `root` | `Element` | Limit discovery and render to this subtree (the root itself is included if it is a region) |
| `silent` | `boolean` | Suppress console logs |
| `force` | `boolean` | Re-process regions already marked `data-birkly-processed` |

Also accepted: `Birkly.processTemplates(element)` as a shorthand for `{ root: element }`.

Late-mounted `<birkly-region>` and `[data-birkly-region]` nodes auto-process after init (custom element `connectedCallback` / mutation observer). Manual `processTemplates({ root })` is for injected markup that is not an attributed region host.

---

### Events

| Event | Target | When |
|-------|--------|------|
| `birkly:region-processed` | The region **element** (`bubbles: true`) | After that region’s HTML/text is rendered |
| `birkly:templates-processed` | `document` | After a **full-page** (unscoped) `processTemplates()` finishes |

```js
el.addEventListener('birkly:region-processed', (e) => {
  // e.detail includes collection / entry / mode when known
});

document.addEventListener('birkly:templates-processed', (e) => {
  // e.detail: { regions, scoped: false }
  BirklyComponents.setActiveNav(document);
});
```

Scoped calls (`{ root }`) emit per-region events but **do not** emit `birkly:templates-processed`.

---

### `site_elements` prefetch

`site_elements` is special-cased for page data:

1. **Always prefetched** — `processTemplates` calls `getAllSiteElements()` (`list_entries` for `collection=site_elements` + current language) and puts the result on the render data as `site_elements` (keyed by `key` / `slug` / `id`).
2. **Page bundle** — `collectBundleRequests` adds a `list_entries` request for `site_elements` when that name appears in the template HTML. Single-entry `{from 'site_elements' get entry '…'}` still adds a `get_entry` request; other collections add `list_entries` when first seen in `{from}` / `{for each}`.
3. **`ensureCollectionsInData`** skips refetching `site_elements` (already provided by step 1).

Templates can use `{site_elements}` / `{from 'site_elements' get entry '…'}` without treating the collection as a normal loop list unless the markup asks for one.

---

### Output placeholders

- **System title** (exists on every entry):

```text
{title}
```

- **User-defined fields** (canonical form):

```text
{'content'}
{'price'}
```

> Rule: system fields may be bare (`{title}`), user-defined fields must be quoted (`{'fieldName'}`).

#### Date formatting (`format` filter)

The **`format` filter is for dates only** (system date fields such as `created_at`, `updated_at`, `published_at`). Truncate / strip_tags are **not** implemented — see [Not implemented](#not-implemented--phantoms).

**Default behavior** (date only, YYYY-MM-DD):

```text
{created_at}
```

**Custom format** (date and time):

```text
{created_at format "Y-m-d H:i"}
```

**Format codes** (PHP-style):

- `Y` = 4-digit year (2025)
- `m` = 2-digit month (01-12)
- `d` = 2-digit day (01-31)
- `H` = 24-hour format (00-23)
- `i` = minutes (00-59)
- `s` = seconds (00-59)
- `F` = Full month name (January, February, etc.)
- `j` = Day without leading zeros (1-31)

**Examples**:

```text
{created_at format "Y-m-d"}           → 2025-01-05 (same as default)
{created_at format "Y-m-d H:i"}        → 2025-01-05 16:45
{created_at format "F j, Y"}           → January 5, 2025
{created_at format "m/d/Y"}            → 01/05/2025
{created_at format "Y-m-d H:i:s"}      → 2025-01-05 16:45:00
```

---

### Loops

#### Basic collection loop

Canonical sentence:

```text
{for each entry in 'blog'}
    {title}
    {'content'}
{endfor}
```

- `'blog'` is the collection name.
- Inside the loop, `entry` refers to the current entry.

#### Nested loops for sub-collections

Example: blog posts with comments:

```text
{for each entry in 'blog'}
    {title}
    {'content'}

    {for each entry in 'comments'}
        {'author'}
        {'content'}
    {endfor}
{endfor}
```

#### Looping a sub-collection for a specific parent entry

```text
{for each entry in 'comments' of 'blog'.'Hello-World'}
```

- `'comments'` is a sub-collection attached to entries in `'blog'`.
- `'Hello-World'` identifies a single entry in `'blog'` by **slug** or **id** (not title).

#### Nested sub-collections (replies on comments)

When a sub-collection has its own sub-collections (e.g. `replies` on `comments`), use the parent sub-entry id from the outer loop:

```text
{for each comment in 'comments' of 'posts'.current}
  {'body'}
  {for each reply in 'replies' of 'comments'.{comment.id} ordered by newest}
    {'body'}
  {endfor}
{endfor}
```

- `'comments'` is the parent sub-collection slug (not the top-level collection).
- `{comment.id}` resolves from the outer loop variable.
- Page bundle / SSR populate nested data under `__sub__:{topCollection}:{topEntryId}:{parentSub}:{parentSubEntryId}:{nestedSub}` keys.

#### Filters on loops

Canonical filter word: **`with`**.

```text
{for each entry in 'blog' with status = published}
{for each entry in 'blog' with 'category' = 'news'}
{for each entry in 'products' with 'price' <= 15}
{for each entry in 'blog' with status = published with 'featured' = '1'}
{for each entry in 'posts' with owner = user_current}
{for each entry in 'posts' with 'owner' = user_current.id}
```

- `status` is a system field.
- Runtime loop filters resolve `user_current` and `user_current.id` from the render context (SSR/page_bundle inject `user_current` when a site session exists).
- Quoted names like `'category'`, `'price'` are user-defined fields.
- Multiple filters: repeat `with` before `ordered by` / `limit`. All clauses are AND-combined.
- Checkbox / toggle fields store JSON booleans. Compare with `'1'`, `'true'`, or `'yes'` for checked rows and `'0'`, `'false'`, or `'no'` for unchecked.

Status values commonly used: `published`, `unpublished`, `scheduled`, `pending`.

Generic field filters: `'fieldName' = 'value'` (and numeric comparisons).

---

### Ordering (G2-04)

Canonical phrase: **`ordered by`** at the end of the loop sentence (after any `with` filters).

```text
{for each entry in 'blog' ordered by newest}
{for each entry in 'blog' ordered by oldest}
{for each entry in 'blog' with status = published ordered by newest}
{for each entry in 'nav-links' ordered by order}
```

| Phrase | Sort field | Direction |
|--------|------------|-----------|
| `ordered by newest` | `created_at` | Newest first |
| `ordered by oldest` | `created_at` | Oldest first |
| `ordered by last-updated` | `updated_at` | Most recently updated first |
| `ordered by order` | custom `order`, else `sort_order` | Ascending numeric |

**Important (G2-04):** `oldest` / `newest` always use **`created_at`**. They do **not** read a custom `order` / `sort_order` field. For manual menu/nav order, use **`ordered by order`** (and ensure entries have `order` or `sort_order` set in the CMS).

On `<birkly-region list>`, the same words go in the attribute: `ordered="order"`, `ordered="newest"`, etc.

#### Single-item selection

```text
{for newest entry in 'blog'}
    ...
{endfor}
```

Conceptually a loop over exactly one entry (the newest by `created_at`).

```text
{for newest entry in 'comments' of 'blog'.newest}
{for each entry in 'comments' of 'blog'.newest ordered by newest}
{from 'blog' get newest entry.title}
```

---

### Existence and field checks

#### Existence of an object

Canonical form (collection + entry, both quoted):

```text
{if 'site_elements'.'hero' exist}
    ...
{endif}
```

- `'site_elements'` is the **collection** name, `'hero'` is the entry **slug** or **id** (not title).
- Older templates may use `{if site_elements.hero exist}`; preferred style is `'collection'.'entry'`.

#### Field presence

```text
{if entry has 'price'}
{if entry has 'price' and 'value' and 'size'}
```

#### Field comparisons

```text
{if entry has 'price' <= 15}
{if entry 'price' <= 15}
```

Allowed comparison operators:

- `=` – equals (canonical; `==` also accepted)
- `!=` – not equals
- `<`, `>` – less than / greater than
- `<=` and `=<` – less than or equal (aliases)
- `>=` and `=>` – greater than or equal (aliases)

> Prefer single `=` for equality (e.g. `current.language = 'de'`).

#### if … or … else

```text
{if entry has 'price' <= 15 or entry has 'discount'}
    ...
{else}
    ...
{endif}
```

---

### From … get … (single entry)

**Canonical** single-entry access is `{from … get …}` / `{endget}`.  
Do **not** use `{entry 'collection'.'slug'}…{endentry}` to load an entry — that form is a phantom (validator rejects it).

**Exception — public forms:** `{entry for 'contact'}…{endentry}` is a **form scaffold** (implemented in `birkly-client.js`). It is not a single-entry load directive. See the public-forms playbook / human doc “Public forms templating”.

#### Block form (multiple fields)

```text
{from 'site_elements' get entry 'blog_headline'}
    <h1>{'content'.0.'value'}</h1>
    <p class="page-subtitle">{'content'.1.'value'}</p>
{endget}
```

- `'site_elements'` — collection name.
- `'blog_headline'` — entry slug or id.
- Inside the block, `entry` is the resolved entry.
- If the entry is missing, the entire block is removed from output.

#### Inline form (single value)

```text
{from 'site_elements'.'blog_headline' get 'content'.0.'value'}
```

Both forms treat collections uniformly — no special syntax for `site_elements` vs `'blog'` (prefetch behaviour differs; see [site_elements prefetch](#site_elements-prefetch)).

#### Array fields on the current entry

For a field named `'tags'` whose value is an array:

```text
{if entry has 'tags'}
{for each item in 'tags'}
    {item}
{endfor}
{endif}
```

```text
{from 'tags' get item.1}          # first item (1-based index)
{from 'tags' get item.newest}     # last item
{from 'tags' get item.oldest}     # first item
{from 'tags' get item.'cms'}      # match by value / key
```

Engine rules:

- `'tags'` is the **field name** on the current entry (API name, not display label).
- `item` is the loop variable for array elements.
- `item.N` is 1-based; `item.newest` / `item.oldest` are last / first.
- `item.'value'` matches string equality or object keys `value` / `name` / `id` / `label`.

---

### System variables and context

#### Current language

```text
{current.language}
```

Resolved from, in order:

1. `lang` URL query parameter (`?lang=de`)
2. `birkly_language` in localStorage
3. Default: `en`

```text
{if current.language = 'de'}
    <p>Willkommen!</p>
{else}
    <p>Welcome!</p>
{endif}
```

**Language switcher** — add `data-birkly-language-select` to a `<select>`:

```html
<select data-birkly-language-select>
    <option value="en">English</option>
    <option value="de">Deutsch</option>
    <option value="fr">Français</option>
</select>
```

The client sets the selected option, updates `?lang=…`, reloads, and persists preference in localStorage.

#### URL query parameters

```text
{query.'lang'}
{query.'slug'}
{query.'collection'}
```

#### Other system variables

- `{current_year}` — current calendar year
- `{site_elements}` — map of site-wide elements (see prefetch)

---

### Current entry and URL context

On single-entry pages (e.g. `?slug=demo-post-1` or `?id=123`), the client resolves:

- **Collection** from `collection` query param, path conventions (e.g. `/demo-website/post.html` → `'blog'`), or other router logic.
- **Current entry** from `slug` or `id`.

Exposed to templates as:

- `entry` (full object) and top-level fields (`title`, `content`, `slug`, …)
- `'blog'.current` — “the current blog entry from URL context”

```text
{title}
{'content'}
{if entry has 'date'}
    ...
{endif}
```

```text
{for each entry in 'comments' of 'blog'.current ordered by newest}
    {'author'}
    {'content'}
{endfor}
```

`.current` is only meaningful when the router/client has resolved that collection for the request. Explicit `'collection'.'identifier'` still uses slug or id.

---

### Enhance-only components (`birkly-components.js`)

Optional helpers that run **after** Light DOM regions have rendered.

**Rule:** enhance only — **never** wipe or replace `innerHTML` of CMS-rendered regions in `connectedCallback`. Do not set `innerHTML` on a host that may contain `{…}` templates or Birkly output. Only add listeners, toggle classes / ARIA, or enhance existing nodes.

```html
<script src="/birkly-client.js"></script>
<script src="/birkly-components.js"></script>
<script>
  document.addEventListener('birkly:templates-processed', function () {
    BirklyComponents.setActiveNav(document);
  });
</script>
```

---

### Plugin extension model

- Plugins can register new template sentences / helpers (e.g. `user_logged_in`, `user_current`, `user_profile`, `user_in_field`).
- At runtime the engine loads:
  1. **Plugin language definitions first.**
  2. **Core/template language definitions last.**

Core language wins on conflicts (CSS-like cascade). Plugins should extend, not override, core phrases.

---

### Not implemented / phantoms

Do **not** use these — they are not in the engine (P45 validator flags phantoms):

| Phantom / missing | Use instead |
|-------------------|-------------|
| `{entry 'blog'.'slug'}…{endentry}` | `{from 'blog' get entry 'slug'}…{endget}` |
| `{ date "F j, Y" }` | `{created_at format "F j, Y"}` |
| `{title truncate 80}` | Dedicated excerpt field, or shorten in CMS |
| `{'content' strip_tags}` | Strip HTML in CMS before publish, or use a plain field |
| `ordered="date:desc"` on regions | `ordered="newest"` / `ordered="order"` |
| `sort=` / `limit=` on `{from … get entries}` | `{for each entry in 'blog' ordered by newest limit 5}` |

**Not a phantom:** `{entry for 'contact'}…{endentry}` — public form scaffold (implemented). The banned form is `{entry 'collection'.'identifier'}…{endentry}` (load-by-slug without `for`).

**Filters today:** `format` for **dates only**. Other filters are deferred (e.g. P46).

---

### Open design questions

- Canonical docs preference for operator aliases (`<=` vs `=<`).
- Richer grammar for complex `with` filters and nested `and` / `or` while staying plain-English.
- Hub/registry file listing active language modules (core + plugins) with conflict diagnostics.
