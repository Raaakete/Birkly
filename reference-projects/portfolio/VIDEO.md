# V-TPL-02 — Portfolio reference site

**Status:** Stub — record walkthrough when ready.

## Story

A designer showcases published projects in a masonry-style gallery. Each project opens a detail page with description and metadata.

## Pages to demo

1. `/reference-projects/portfolio/website/index.html` — project gallery
2. `/reference-projects/portfolio/website/project.html?slug=brand-identity-acme` — project detail

## Talking points

- Collection loop with `featured` flag
- Slug-based detail via `birkly-page` + `birkly-collection` meta
- Minimal CSS grid gallery layout

## Prerequisites

```bash
./scripts/install-reference-project.sh portfolio
```
