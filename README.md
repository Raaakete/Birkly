# Birkly

A flat-file, modular, zero-dependency CMS — upload and run. No npm, no database, no build step.

## Quick start

Open **`0_START-HERE.html`** after extracting a release zip, or run locally:

```bash
chmod +x ./start-server.sh
./start-server.sh
```

Admin: [http://localhost:8080/admin](http://localhost:8080/admin)

## Documentation

- **[Birkly-Docs](https://github.com/Raaakete/Birkly-Docs)** — guides, API reference, plugin economy
- **[CONTRIBUTING.md](CONTRIBUTING.md)** — issues and pull requests

## Project layout

- `/admin/` — Admin UI (HTML/CSS/JS)
- `/api/` — PHP API endpoints (content, media, users, settings)
- `/content/` — Flat-file content storage (JSON/Markdown)
- `/media/` — Uploaded files
- `/users/` — User data (JSON)
- `/settings/` — Site settings (JSON)
- `/project/` — Public site files (themes, assets)

## Local development

See **Quick start** above. Official plugins for development:

```bash
./scripts/install-dev-plugins.sh
```

## Production

1. Upload all files to your web server (Apache, Nginx, etc.).
2. For Apache, the included `.htaccess` ensures everything works automatically.
3. For Nginx, see the included `nginx.conf.sample` for a working config.

- The admin panel is always available at `/admin`.
- API endpoints are available at `/api/*`.
- No extra setup is required for most shared hosting or local stacks.

## Default Admin Login

After running `php scripts/create-default-user.php` to create the default admin user:

- Email: `admin@localhost`
- Password: `password`

**Important:** Change the password after your first login.

---

## Templating language

## 1. Loops (Listing Entries)

```
{for each entry in 'collection' sort="created_at:desc" filter="image AND status=published" limit=5 offset=2}
  <h2>{title}</h2>
  <img src="{image}" />
  <p>{content}</p>
  <span>{index}</span>
{/for}
```
- **sort**: `field:asc`, `field:desc`, `first:field:value`, `last:field:value`, `random`, `newest-first`, `oldest-first`, multi-level (comma-separated)
- **filter**: `field`, `field=value`, `field!=value`, `field>value`, `field<value`, `!field`, combine with `AND`
- **limit**: max number of entries
- **offset**: skip first N entries
- **index**: current entry index (starts at 1)

## 2. Conditionals

```
{if image}
  <img src="{image}" />
{else}
  <span>No image</span>
{/if}
```
- Renders the first block if the field is truthy, otherwise the `{else}` block (optional).

## 3. Filters

```
{title|escape|default:Untitled}
{created_at|date:YYYY-MM-DD}
```
- **escape**: HTML-escapes the value
- **raw**: disables escaping
- **default:VALUE**: fallback if value is empty
- **date:FORMAT**: formats date (e.g. `YYYY-MM-DD`, `DD.MM.YYYY HH:mm`)

## 4. Single Data Fetch

```
{from 'collection' get 'entry, field'}
```
- Example: `{from 'users' get 'admin, display_name'}`
- Fetches a single field from a specific entry in a collection (by id or slug).

## 5. Example: Advanced Sorting

```
{for each entry in 'blog' sort="first:featured:true,created_at:desc"}
  <h2>{title}</h2>
{/for}
```
- Shows all `featured:true` entries first, then sorts by newest.

---

**Note:** All features are dependency-free and work in the browser. For more advanced logic, use JavaScript or your static site generator. 