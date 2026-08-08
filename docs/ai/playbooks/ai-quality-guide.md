# AI quality guide — how to produce excellent Birkly outputs

Read this when you want reliable, production-ready results — especially for casual user prompts like "build me a bakery website."

## Golden rules

1. **Read before write** — One `read_collection` / `get_project_info` / playbook load beats ten guesses. Field slugs, URL prefixes, and existing files must match reality.
2. **Schema before entries before HTML** — Never `create_entry` with empty `fields: []`. Never write templates referencing fields that do not exist.
3. **Follow playbooks, adapt patterns** — Playbooks are principles + examples, not copy-paste recipes. Infer content model from the user's business.
4. **Execute, don't narrate** — Use tool actions. Do not claim files were written without a successful tool result.
5. **One discovery pass per turn** — If playbooks or project discovery are pre-loaded in the system prompt, do not call `get_project_info` / `list_project_files` again unless the user changed something.
6. **Verify wiring** — Every templated HTML page needs `<script src="/birkly-client.js"></script>`. List pages loop; detail pages use URL slug context (no collection loop on detail).

## Decision flow (every task)

```
User request
  ├─ Unclear scope? → read_birkly_docs "playbooks-index"
  ├─ Building a public site? → web-project-patterns + site-building (+ content-model, templating)
  ├─ Collections/schema? → content-model (+ fieldtypes for field types)
  ├─ Forms / contact / comments? → public-forms (+ sub-collections if nested)
  ├─ Plugin feature? → plugins-overview first, then plugin playbook
  ├─ Connect external AI? → mcp-setup or ai-connections
  └─ Then: discover once → schema → data → HTML → verify
```

## Common failure modes (avoid these)

| Symptom | Cause | Fix |
|---------|-------|-----|
| Raw `{title}` in browser | Missing `birkly-client.js` | Add script tag to every templated page |
| Blank detail page | Empty `{slug}` in links | `create_entry` stores slug; use `?collection=&slug=` |
| "Validation failed" on forms | Form `name=` ≠ schema field slugs | `read_collection` first; match exactly |
| Send button does nothing | `{entry for}` without client, or wrong JS | Use `{entry for}` + birkly-client (wired) or `data-birkly-url` form |
| Comments invisible | Draft status (`auto_publish: false`) | Set `auto_publish: true` or approve in admin |
| Unstyled site | CSS path `/css/` instead of `/project/css/` | Use `get_project_info` → `site_url_prefix` |
| Detail page shows all entries | Loop on detail HTML | Detail = URL context only, no `{for each entry in …}` |
| Wrong dates / ordering | Phantom helpers | `{created_at format "…"}`; `ordered by newest` / `ordered="newest"` |
| Form vs load phantom | Confused `{entry…}` | Forms: `{entry for 'slug'}`; load entry: `{from … get entry …}` |
| Wrong field type in schema | Guessing types | `read_birkly_docs` topic `fieldtypes` |

## Cross-reference map (where to read depth)

| Need | Playbook topic | Human docs (docs.birkly.com) |
|------|----------------|------------------------------|
| Content model | `content-model` | /02-content/content-model-overview.html |
| Field types | `fieldtypes` | /02-content/field-types.html |
| Template syntax | `templating` | /03-templating/canonical-syntax.html (engine: `docs/TEMPLATE_LANGUAGE_SPEC.md`) |
| Public forms | `public-forms` | /03-templating/public-forms-templating.html · /04-integration/public-forms.html |
| MCP / Cursor | `mcp-setup` | /01-admin/mcp-connections.html |
| Media | `media` | /01-admin/media-library.html |
| Commerce | `commerce`, `commerce-store-kit` | /06-extensibility/plugins/commerce.html |
| Collection sidebar groups | `collection-groups` | /02-content/collection-groups.html |
| Rich text fields | `content-editor` | /02-content/content-editor.html |
| Plugins | `plugins-overview` | /06-extensibility/marketplace-go-live.html |

Load via `read_birkly_docs` with the topic slug. MCP: `birkly://docs/{topic}`.

## Site build checklist (minimum viable)

- [ ] Content model: collections + fields defined (`read_collection` to confirm)
- [ ] Seed entries with `status: "published"`
- [ ] `index.html` + at least one list loop
- [ ] Detail page(s) with meta `birkly-collection` + slug URL pattern
- [ ] Shared CSS under `css/style.css` (paths use site prefix)
- [ ] `birkly-client.js` on every page with `{…}` tags
- [ ] Contact/form collections: `entry_method: public_api` if visitors submit
- [ ] Optional: sub-collections for comments with `auto_publish: true` if instant display wanted

## Tone for end users

Assume the user is **not technical**. Do not ask them to read docs, run MCP setup steps they already completed, or manually fix things the AI tools can do (collection settings, public API, groups). Execute via tools; explain outcomes in plain language.

## When stuck

1. `read_birkly_docs` → most specific playbook for the task
2. `read_collection` / `list_collections` / `get_project_info` — ground truth
3. `read_project_file` — see what HTML actually exists
4. Do not invent template syntax, API endpoints, or field type names
