# SSR and build-time

Render Birkly content on the server or at build time so visitors get HTML without waiting on client-side template discovery.

## Summary

Fetch published content from the **Public API** or **`page_bundle.php`**, then render with PHP, Astro, Next.js, or another SSR/SSG stack. Use this for SEO, non-JS fallbacks, and static hosting. Runnable patterns below; for in-browser templates see [Website integration](website-integration.md).

## Beginner

**Idea:** your server (or build job) asks Birkly for JSON, builds HTML, and sends that HTML to the browser. Visitors see content even if JavaScript is off.

| Stack | Typical approach |
|-------|------------------|
| **PHP** | `file_get_contents` / cURL → JSON → `echo` fields (or pass into your PHP templates) |
| **Astro** | `fetch` in frontmatter → `.map` into components |
| **Next.js** | `fetch` in a Server Component or `getStaticProps` → React |

Decision hub: [Choose your stack](choose-your-stack.md).

## Advanced Users

### Public API — one entry (any language)

```
GET https://your-cms.com/api/public.php?action=get_entry&collection=blog&slug=my-post-slug
→ { "status": "ok", "data": { "entry": { "title", "slug", "content", … } } }
```

### Page bundle — several collections in one round-trip

```
POST https://your-cms.com/api/page_bundle.php
Content-Type: application/json

{
  "language": "en",
  "requests": [
    { "type": "list_entries", "into": "blog", "collection": "blog" },
    { "type": "get_entry", "into": "home", "collection": "pages", "slug": "home" }
  ]
}
```

### PHP (runnable sketch)

Save as `blog-post.php` on a host that can reach your CMS (replace URLs/slug):

```php
<?php
declare(strict_types=1);

$cms = 'https://your-cms.com';
$slug = preg_replace('/[^a-z0-9\-]/', '', $_GET['slug'] ?? 'hello-world') ?: 'hello-world';
$url = $cms . '/api/public.php?action=get_entry&collection=blog&slug=' . rawurlencode($slug);

$raw = file_get_contents($url, false, stream_context_create([
    'http' => ['timeout' => 8, 'header' => "Accept: application/json\r\n"],
]));
$body = json_decode($raw ?: '', true);
$entry = ($body['status'] ?? '') === 'ok' ? ($body['data']['entry'] ?? null) : null;

if (!$entry) {
    http_response_code(404);
    echo '<!DOCTYPE html><title>Not found</title><p>Post not found.</p>';
    exit;
}

$title = htmlspecialchars((string)($entry['title'] ?? ''), ENT_QUOTES, 'UTF-8');
// Editor HTML is intentional; only use trusted CMS content.
$content = (string)($entry['content'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= $title ?></title>
</head>
<body>
  <article>
    <h1><?= $title ?></h1>
    <div class="content"><?= $content ?></div>
  </article>
</body>
</html>
```

### Astro (build / SSR)

```astro
---
// src/pages/blog/[slug].astro
const CMS = import.meta.env.PUBLIC_BIRKLY_CMS_URL || 'https://your-cms.com';
const { slug } = Astro.params;
const res = await fetch(
  `${CMS}/api/public.php?action=get_entry&collection=blog&slug=${encodeURIComponent(slug)}`
);
const body = await res.json();
if (body.status !== 'ok' || !body.data?.entry) {
  return Astro.redirect('/404');
}
const entry = body.data.entry;
---
<html lang="en">
  <head><title>{entry.title}</title></head>
  <body>
    <article>
      <h1>{entry.title}</h1>
      <div set:html={entry.content} />
    </article>
  </body>
</html>
```

Blog index with bundle:

```astro
---
const CMS = import.meta.env.PUBLIC_BIRKLY_CMS_URL;
const res = await fetch(`${CMS}/api/page_bundle.php`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    language: 'en',
    requests: [{ type: 'list_entries', into: 'blog', collection: 'blog' }],
  }),
});
const bundle = await res.json();
const posts = bundle.data?.blog || [];
---
<ul>
  {posts.map((p) => (
    <li><a href={`/blog/${p.slug}`}>{p.title}</a></li>
  ))}
</ul>
```

### Next.js (App Router server component)

```tsx
// app/blog/[slug]/page.tsx
const CMS = process.env.BIRKLY_CMS_URL!;

export default async function BlogPost({ params }: { params: { slug: string } }) {
  const res = await fetch(
    `${CMS}/api/public.php?action=get_entry&collection=blog&slug=${encodeURIComponent(params.slug)}`,
    { next: { revalidate: 60 } }
  );
  const body = await res.json();
  const entry = body.status === 'ok' ? body.data?.entry : null;
  if (!entry) return <p>Not found</p>;
  return (
    <article>
      <h1>{entry.title}</h1>
      <div dangerouslySetInnerHTML={{ __html: entry.content || '' }} />
    </article>
  );
}
```

### Notes

- **Published only** — public endpoints omit drafts.
- **CORS** matters for browser fetches; server-side `fetch` / PHP usually does not need CORS.
- Client-side `Birkly.processTemplates` is optional on SSR pages if HTML is already final; keep `birkly-client.js` only for interactive islands.
- String engine in Node is optional/advanced (`Birkly.render` / `renderEngine`) — most SSR stacks map JSON fields directly.

## Related

- [Choose your stack](choose-your-stack.md)
- [Public API](public-api.md)
- [Client library](client-library.md)
- [SPA (React / Vue)](spa-react-vue.md)
- [Website integration](website-integration.md)
- [Production optimization](../03-templating/production-optimization.md)
