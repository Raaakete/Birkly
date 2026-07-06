# Plugin admin UI guide

**Phase 7 (P7-M0)** — mandatory contract for every Birkly plugin admin page.

## Layout reference

Match `admin/templates/entries.html`:

1. **Breadcrumb only** in `page-header` — no Settings or Dashboard parent
2. **No duplicate `<h1>`** in the page body — the breadcrumb current segment is the title
3. **`main-actions-bar`** for primary actions (buttons), not a second title row
4. **Tabs** switch content only — they do **not** change the breadcrumb

## Registration

In your plugin `plugin.php`:

```php
register_plugin_admin_meta('commerce', [
    'title' => 'Commerce',
    'root_page' => 'commerce',
    'permission' => 'manage_commerce',
    'page_slugs' => ['commerce'],
    'sidebar' => [
        'show' => true,
        'label' => 'Commerce',
        'order' => 20,
        'icon' => '<svg>...</svg>',
    ],
]);
```

### Sub-pages (separate admin routes)

Only **separate routes** extend the breadcrumb — not in-tab switches.

```php
'sub_pages' => [
    'user_tiers' => ['title' => 'Tiers', 'permission' => 'manage_users'],
    'user_groups' => ['title' => 'Groups'],
],
'page_slugs' => ['site_users', 'user_tiers', 'user_groups'],
```

| Page | Breadcrumb |
|------|------------|
| `?page=site_users` (any tab) | `Site Users` |
| `?page=user_tiers` | `Site Users › Tiers` |
| `?page=commerce` (any tab) | `Commerce` |

## Template usage

Directive-based plugin HTML (`commerce.html`):

```html
<link rel="stylesheet" href="{birkly_url '/admin/css/plugin-page.css'}">
{plugin_page_header}
<div class="payments-page commerce-page plugin-page">
  <!-- tabs + panels — no h1 -->
</div>
```

PHP plugin pages can include:

```php
<?php include __DIR__ . '/../../../admin/partials/plugin_page_shell.php'; ?>
```

## CSS

Load `admin/css/plugin-page.css` on every plugin admin page. Use class `plugin-page` on the root content wrapper.

## Sidebar

When `register_plugin_admin_meta()` is used with `sidebar.show => true`, core sidebar reads from the **admin shelf** instead of `plugin.json` navigation. Legacy `plugin.json` navigation is fallback only.

## Permissions

Set `permission` on meta; core enforces it via `birkly_plugin_admin_page_permission()` before rendering the page.

## Docs shelf

Register AI + Help docs separately via `register_plugin_docs()` — see `core/plugins/docs_shelf.php`.
