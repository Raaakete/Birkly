# SPA (React / Vue)

Build a single-page app that loads Birkly content as JSON and renders with your framework — not with `{for each}` inside JSX.

## Summary

For React, Vue, and similar SPAs: call the **Public API** or `Birkly.fetch` / `Birkly.getEntryBySlug`, then render with components. **Do not** put Birkly template tags in JSX. Optional: load `birkly-client.js` and use `Birkly.render(html, data)` into a DOM ref when you want the string engine without Light DOM discovery.

## Beginner

### The rule

| Do | Don’t |
|----|-------|
| `fetch` JSON → map to `<Article />` | `{for each entry in 'blog'}` inside JSX |
| `Birkly.getEntryBySlug('blog', slug)` | `{from 'blog' get entry …}` as React children |
| Configure CORS for your app origin | Expect `processTemplates()` to walk React’s virtual DOM |

### Minimal React hook (Public API)

```jsx
import { useEffect, useState } from 'react';

const CMS = 'https://your-cms.com';

export function useBlogEntry(slug) {
  const [entry, setEntry] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    let cancelled = false;
    const url =
      `${CMS}/api/public.php?action=get_entry` +
      `&collection=blog&slug=${encodeURIComponent(slug)}`;

    fetch(url)
      .then((r) => r.json())
      .then((body) => {
        if (cancelled) return;
        if (body.status !== 'ok') throw new Error(body.message || 'API error');
        setEntry(body.data?.entry ?? null);
      })
      .catch((e) => !cancelled && setError(e));

    return () => { cancelled = true; };
  }, [slug]);

  return { entry, error };
}

export function BlogPost({ slug }) {
  const { entry, error } = useBlogEntry(slug);
  if (error) return <p>Could not load post.</p>;
  if (!entry) return <p>Loading…</p>;
  return (
    <article>
      <h1>{entry.title}</h1>
      <div dangerouslySetInnerHTML={{ __html: entry.content || '' }} />
    </article>
  );
}
```

### List with React (not a template loop)

```jsx
function BlogIndex() {
  const [entries, setEntries] = useState([]);
  useEffect(() => {
    fetch(`${CMS}/api/public.php?action=list_entries&collection=blog`)
      .then((r) => r.json())
      .then((body) => setEntries(body.data?.entries || []));
  }, []);
  return (
    <ul>
      {entries.map((e) => (
        <li key={e.id || e.slug}>
          <a href={`/blog/${e.slug}`}>{e.title}</a>
        </li>
      ))}
    </ul>
  );
}
```

### Optional: Birkly client helpers

```html
<script src="https://your-cms.com/birkly-client.js"
        data-cms-url="https://your-cms.com"></script>
```

```js
// After the script loads:
const entry = await Birkly.getEntryBySlug('blog', 'my-post-slug');
const list = await Birkly.fetch('list_entries', { collection: 'blog' });
```

### Optional: `Birkly.render` into a ref

When you have a template **string** (not JSX tags) and JSON data:

```jsx
function CmsFragment({ template, data }) {
  const ref = useRef(null);
  useEffect(() => {
    if (!ref.current || !window.Birkly?.render) return;
    ref.current.innerHTML = Birkly.render(template, data);
  }, [template, data]);
  return <div ref={ref} />;
}

// template = "<h1>{title}</h1><article>{'content'}</article>"
// data = entry object from the API
```

Still do **not** write `{for each}` as JSX syntax — only as a string if you intentionally use the Birkly string engine.

## Advanced Users

### CORS

The browser origin of your SPA (e.g. `https://app.example.com`) must be allowed by Birkly / your reverse proxy. Failed CORS looks like a network error in DevTools, not a JSON body.

### API shapes

- List: `GET /api/public.php?action=list_entries&collection=blog`
- One entry: `action=get_entry&collection=blog&slug=…`
- Batch: `POST /api/page_bundle.php` with a `requests` array (see [Client library](client-library.md) / [Public API](public-api.md))

`Birkly.fetch(action, params)` wraps the public endpoint. Prefer framework-owned state for routing and caching; use the client when you already load `birkly-client.js`.

### Why not Light DOM `processTemplates` in SPA roots?

`processTemplates` discovers **DOM** regions with template text. React/Vue continuously reconcile their trees — mixing Birkly `innerHTML` replacement with virtual DOM usually fights the framework. Keep Birkly tags on static HTML pages; keep SPAs on JSON.

### Vue sketch

```js
import { ref, onMounted } from 'vue';

export function useEntries(collection) {
  const entries = ref([]);
  onMounted(async () => {
    const res = await fetch(
      `https://your-cms.com/api/public.php?action=list_entries&collection=${collection}`
    );
    const body = await res.json();
    entries.value = body.data?.entries || [];
  });
  return { entries };
}
```

## Related

- [Choose your stack](choose-your-stack.md)
- [Public API](public-api.md)
- [Client library](client-library.md)
- [SSR and build-time](ssr-and-build-time.md)
- [Website integration](website-integration.md)
