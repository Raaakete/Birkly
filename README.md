# HaruCMS (PHP Version)

A flat-file, modular, zero-dependency CMS designed for simplicity, security, and extensibility. Just upload and use—no npm, no database, no server setup required.

## 🚀 Quick Start

**New to HaruCMS?** Open `0_START-HERE.html` in your browser for a complete setup guide with step-by-step instructions for local development or web server deployment.

## Project Structure

- `/admin/` — Admin UI (HTML/CSS/JS)
- `/api/` — PHP API endpoints (content, media, users, settings)
- `/content/` — Flat-file content storage (JSON/Markdown)
- `/media/` — Uploaded files
- `/users/` — User data (JSON)
- `/settings/` — Site settings (JSON)
- `/themes/` — (Optional) Themes/templates
- `/plugins/` — (Optional) PHP plugins
- `index.php` — Entry point (optional, for routing)

## Getting Started

### Local Development

1. Download and extract HaruCMS.
2. Open a terminal in the extracted folder and run:
   ```
   chmod +x ./start-server.sh
   ./start-server.sh
   ```
   Or directly with PHP (requires the router script):
   ```
   php -S localhost:8080 -t . server-router.php
   ```
3. Visit [http://localhost:8080/admin](http://localhost:8080/admin) in your browser.

- No configuration is needed for most local environments (XAMPP, MAMP, Laragon, Herd, Valet, etc.).
- All static files and API endpoints work out of the box.

### Production/Deployment

1. Upload all files to your web server (Apache, Nginx, etc.).
2. For Apache, the included `.htaccess` ensures everything works automatically.
3. For Nginx, see the included `nginx.conf.sample` for a working config.

- The admin panel is always available at `/admin`.
- API endpoints are available at `/api/*`.
- No extra setup is required for most shared hosting or local stacks.

## Default Admin Login

- Username/Email: `admin@localhost`
- Password: `admin`

**Important:** Change the password after your first login for security.

---

For advanced configuration, see the documentation or contact the HaruCMS team.

## Next Steps
- Migrate/reuse admin UI HTML/CSS/JS into `/admin/`
- Scaffold basic PHP API endpoints in `/api/`
- Implement flat-file storage for content, users, media, and settings
- Connect admin UI to PHP endpoints via AJAX
- Test and polish the new PHP-based CMS
- Update documentation and usage instructions

---

# HaruCMS Templating Language

HaruCMS provides a powerful, zero-dependency templating language for rendering content in your static or headless sites. Below are all supported features and syntax:

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