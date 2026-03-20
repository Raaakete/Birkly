## Birkly Template Language – Draft Spec (v0.1)

This is a living draft for the plain‑English template language used by the demo sites. It will be iterated during the alpha.

### Core ideas

- **Plain English**: Templates are written as readable sentences.
- **Collections** are always written in **single quotes**: `'blog'`, `'comments'`, `'services'`.
- **Entries** are the items inside a collection and are normally referred to as `entry` inside loops.
- **Fields**:
  - System fields like `title` don’t use quotes.
  - User‑defined fields are referenced in quotes: `{'content'}`, `{'price'}`.
- **Plugins** can extend the language with extra sentences/keywords, but the **core language is loaded last** so core keywords win on conflicts.

---

### Output placeholders

- **System title** (exists on every entry):

```text
{title}
```

- **User‑defined fields** (canonical form):

```text
{'content'}
{'price'}
```

> Rule: system fields may be bare (`{title}`), user‑defined fields must be quoted (`{'fieldName'}`).

#### Date and Time Formatting

System date fields (`created_at`, `updated_at`, `published_at`) can be formatted using the `format` filter:

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

#### Nested loops for sub‑collections

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

#### Looping a sub‑collection for a specific parent entry

Target comments of a specific blog entry:

```text
{for each entry in 'comments' of 'blog'.'Hello-World'}
```

Semantics (to be refined):

- `'comments'` is a sub‑collection attached to entries in `'blog'`.
- `'Hello-World'` identifies a single entry in `'blog'` (likely by slug or title).

---

### Existence and field checks

#### Existence of an object

Canonical form (collection + entry, both quoted):

```text
{if 'site_elements'.'hero' exist}
    ...
{endif}
```

Notes:

- `'site_elements'` is the **collection** name, `'hero'` is the **entry** identifier in that collection.
- The entry identifier may be either the entry **slug** or the entry **id**; human‑readable titles are **not** used as identifiers.
- Older templates may use a dot path like `{if site_elements.hero exist}`; this will remain supported for compatibility but `'collection'.'entry'` is the preferred style.

#### Field presence

```text
{if entry has 'price'}
{if entry has 'price' and 'value' and 'size'}
```

#### Field comparisons

Examples:

```text
{if entry has 'price' <= 15}
{if entry 'price' <= 15}
```

Allowed comparison operators:

- `=` – equals (canonical; `==` is also accepted for compatibility)
- `!=` – not equals
- `<`, `>` – less than / greater than
- `<=` and `=<` – less than or equal (aliases)
- `>=` and `=>` – greater than or equal (aliases)

> **Note**: Use single `=` for equality comparisons (e.g., `current.language = 'de'`). The double `==` is also accepted but `=` is the preferred, plain-English style.

One of each pair (`<=` vs `=<`, `>=` vs `=>`) will be chosen as the canonical style for docs, but both are accepted.

---

### States and ordering (newest / oldest / status)

Built‑in “state words” let you talk about ordering and status in English.

#### Filters on loops

Canonical filter word: **`with`**.

Examples:

```text
{for each entry in 'blog' with status = published}
{for each entry in 'blog' with 'category' = 'news'}
{for each entry in 'products' with 'price' <= 15}
```

- `status` is a system field.
- Quoted names like `'category'`, `'price'` are user‑defined fields.

Planned status filters:

- `status = published`
- `status = unpublished`
- `status = scheduled`
- `status = pending`

Generic field filters:

- `'fieldName' = 'value'`

Multiple filters and `and`/`or` combinations will be defined in a later revision.

#### Ordering in loops (lists)

Canonical ordering phrase for lists: **`ordered by`**.

Examples:

```text
{for each entry in 'blog' ordered by newest}
{for each entry in 'blog' ordered by oldest}
{for each entry in 'blog' with status = published ordered by newest}
{for each entry in 'comments' of 'blog'.newest ordered by newest}
```

Supported order words (draft):

- `newest`
- `oldest`
- `last-updated`

Ordering always comes at the end of the loop sentence, after any `with` filters.

#### Single‑item selection

For “the newest entry” instead of a whole list, a special form is allowed:

```text
{for newest entry in 'blog'}
    ...
{endfor}
```

Conceptually this is a loop over exactly one entry (the newest in the collection).

This pattern can also be combined with sub‑collections:

```text
{for newest entry in 'comments' of 'blog'.newest}
    ...
{endfor}
```

Examples tying everything together:

```text
{for each entry in 'comments' of 'blog'.newest ordered by newest}
{for newest entry in 'comments' of 'blog'.newest}
{from 'blog' get newest entry.title}
```

---

### From … get … (single entry access)

Two forms are supported for accessing a single entry and its fields:

#### Block form (for multiple fields)

```text
{from 'site_elements' get entry 'blog_headline'}
    <h1>{'content'.0.'value'}</h1>
    <p class="page-subtitle">{'content'.1.'value'}</p>
{endget}
```

- `'site_elements'` is the collection name.
- `'blog_headline'` is the entry identifier (slug or id).
- Inside the block, `entry` refers to the resolved entry, and you can access fields using `{'field'.'subfield'}` syntax.
- If the entry is not found, the entire block is removed from output.

#### Inline form (for single values)

```text
{from 'site_elements'.'blog_headline' get 'content'.0.'value'}
```

- `'site_elements'.'blog_headline'` identifies the collection and entry.
- `'content'.0.'value'` is the field path to retrieve.
- Returns the value directly (or empty string if entry/field not found).

Both forms treat collections uniformly—there are no special rules for `site_elements` vs `'blog'` vs any other collection.

#### Array fields on the current entry (nested field data)

For any field on the current entry whose **field name** (API name) is `'tags'` and whose value is an array:

- Loop over items:

```text
{if entry has 'tags'}
{for each item in 'tags'}
    {item}
{endfor}
{endif}
```

- Get a specific item:

```text
{from 'tags' get item.1}          # first item (1-based index)
{from 'tags' get item.newest}     # last item
{from 'tags' get item.oldest}     # first item
{from 'tags' get item.'cms'}      # match by value / key
```

Engine rules:

- `'tags'` refers to the **field name** on the current entry (not the display label).
- `item` is the canonical loop variable name for array elements.
- `item.N` selects the Nth array element (1-based).
- `item.newest` / `item.oldest` select last / first element.
- `item.'value'` selects the first element that matches:
  - For string arrays: the string equals `'value'`.
  - For object arrays: a canonical key such as `value`, `name`, `id`, or `label` equals `'value'`.

- **if … or … else / and …**

Goal: readable logic such as:

```text
{if entry has 'price' <= 15 or entry has 'discount'}
    ...
{else}
    ...
{endif}
```

Exact English shapes for `and`/`or` combinations will be fully documented in a later version.

---

### System variables and context

Templates have access to system variables that provide context about the current request:

#### Current language

The current language is available as:

```text
{current.language}
```

This returns the language code (e.g., `en`, `de`) determined by:
1. The `lang` URL query parameter (`?lang=de`)
2. The `birkly_language` value stored in localStorage
3. Default: `en`

**Example usage:**

```text
{if current.language = 'de'}
    <p>Willkommen!</p>
{else}
    <p>Welcome!</p>
{endif}
```

**Language switcher (no custom JavaScript required):**

To create a language switcher dropdown, simply add the `data-birkly-language-select` attribute to a `<select>` element:

```html
<select data-birkly-language-select>
    <option value="en">English</option>
    <option value="de">Deutsch</option>
    <option value="fr">Français</option>
</select>
```

The Birkly client will automatically:
- Set the selected option based on the current language
- Handle language changes by updating the URL (`?lang=...`) and reloading the page
- Persist the language preference in localStorage

No custom JavaScript needed!

#### URL query parameters

All URL query parameters are accessible via `query.*`:

```text
{query.'lang'}        → Value of ?lang=... (or empty)
{query.'slug'}       → Value of ?slug=... (or empty)
{query.'collection'}  → Value of ?collection=... (or empty)
```

**Example:**

```text
{if query.'lang' = 'de'}
    <p>Deutsche Version</p>
{endif}
```

#### Other system variables

- `{current_year}` → Current year (e.g., `2025`)
- `{site_elements}` → Object containing site-wide content elements (see "Site elements" section)

---

### Current entry and URL context (single‑entry pages)

On pages like a blog post detail view, the URL (for example `?slug=demo-post-1` or `?id=123`) determines the **current entry** for a collection.

Runtime rules (implemented in JS/PHP, not written out in templates):

- The system resolves a **collection** (e.g. `'blog'`) from:
  - an explicit `collection` query parameter, or
  - URL/path conventions (e.g. `/demo-website/post.html` defaults to `'blog'`), or
  - other router logic.
- The system resolves the **current entry** in that collection from either:
  - a `slug` query parameter, or
  - an `id` query parameter.
- That current entry is exposed to templates as:
  - `entry` (full object),
  - top‑level fields like `title`, `content`, `slug`, etc.,
  - and via the special suffix **`.current`** on the collection:
    - `'blog'.current` → “the current blog entry from URL context”.

Examples:

- Basic single‑entry template (no explicit URL syntax needed):

```text
{title}
{'content'}
{if entry has 'date'}
    ...
{endif}
```

- Sub‑entries (e.g. comments of the current blogpost):

```text
{for each entry in 'comments' of 'blog'.current ordered by newest}
    {'author'}
    {'content'}
{endfor}
```

Notes:

- `.current` is only meaningful on collections that the router / client has resolved for this request.
- Identifiers in `'collection'.'identifier'` still use slug or id explicitly; `.current` is a shortcut that says “use whatever the URL resolved”.

---

### Plugin extension model (draft)

- Plugins can register new template sentences / helpers (e.g. `user_logged_in`, `user_current`, `user_profile`, `user_in_field`).
- At runtime the engine loads:
  1. **Plugin language definitions first.**
  2. **Core/template language definitions last.**

This “CSS‑like” ordering means:

- Core language has the final say for any conflicting keyword or sentence.
- Plugins are encouraged to extend, not override, core phrases.

A future revision will define:

- A “hub” or registry file that lists all active language modules (core + plugins).
- Rules for conflict detection and clear error messages when a plugin tries to redefine a core sentence.

---

### Open design questions (for future iterations)

- Exact rules for identifying a specific entry in patterns like `'blog'.'Hello-World'` (slug vs title vs ID).
- Canonical operator syntax: which variants (`<=` vs `=<`) we show in docs.
- Full grammar for:
  - `from … get …`
  - complex filters (`where status = published and 'price' <= 15`)
  - boolean logic with `and` / `or` that stays very readable.

