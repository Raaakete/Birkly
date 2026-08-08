# Detail pages and URL context

How a single-entry page (blog post, product, event) reads the **current** entry from the URL — and the #1 anti-pattern: looping the whole collection on the detail page.

## Summary

**In short:** List pages link with `?collection=YOUR_COLL&slug={slug}`. The detail page prints `{title}`, `{'content'}`, etc. for **that** entry — it must **not** wrap the body in `{for each entry in 'YOUR_COLL'}`. Sub-collections use `of 'blog'.current`.

![Correct list→detail vs anti-pattern looping the collection](../media/03-templating/detail-pages-and-url-context/screenshots/list-detail-correct-vs-antipattern.svg)

> **Video (follow-along):** multi-step list → detail → back — `media/03-templating/detail-pages-and-url-context/videos/`

## Beginner

### List → detail link

On the list page, inside a loop:

```html
{for each entry in 'blog' with status = published ordered by newest}
  <a href="post.html?collection=blog&slug={slug}">{title}</a>
{endfor}
```

Prefer **`slug`** (auto-generated from title). Avoid `?id=` unless you have a reason.

### Detail page (correct)

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="birkly-collection" content="blog">
  <title>{title}</title>
</head>
<body>
  <p><a href="blog.html">← Back to blog</a></p>
  <article>
    <h1>{title}</h1>
    <time>{created_at format "F j, Y"}</time>
    <div>{'content'}</div>
  </article>

  <section class="comments">
    {for each entry in 'comments' of 'blog'.current ordered by newest}
      <p><strong>{'author'}</strong> {'content'}</p>
    {endfor}
  </section>

  <script src="/birkly-client.js"></script>
</body>
</html>
```

### Detail page (wrong — never do this)

```html
<!-- WRONG: always shows a list or the wrong item -->
{for each entry in 'blog'}
  <h1>{title}</h1>
  <div>{'content'}</div>
{endfor}
```

### What `.current` means

`'blog'.current` means “the blog entry resolved from this page’s URL.” It only works when the client knows the collection (query `collection=`, meta tag, or path convention).

## Advanced Users

### For developers & AI

**Resolution**

1. Collection from `collection` query param, `meta name="birkly-collection"`, or path heuristics.  
2. Entry from `slug` or `id`.  
3. Exposed as top-level fields + `entry` + `'collection'.current`.

**Template access**

```text
{title}
{'content'}
{for each entry in 'comments' of 'blog'.current ordered by newest}
```

Explicit alternative: `of 'blog'.'my-post-slug'` (slug or id, not title).

**Query placeholders**

```html
{query.'slug'}
{query.'collection'}
```

**Known bad patterns (regressions)**

| Bad | Fix |
|-----|-----|
| Detail loops entire collection | URL context only |
| List links without `collection` when needed | `?collection=&slug=` |
| Custom `fetch` that ignores router | Use client + query params |

Also see [From…get](from-get-and-existence.md) when loading a named entry without URL context.

## Related

- [Loops and lists](loops-and-lists.md)
- [Sub-collections in templates](sub-collections-in-templates.md)
- [Client setup](client-setup.md)
- [Worked site walkthrough](worked-site-walkthrough.md)
- [Reference projects](reference-projects.md)
