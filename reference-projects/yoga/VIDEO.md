# V-TPL-03 — Yoga studio reference site

**Status:** Stub — record walkthrough when ready.

## Story

A yoga studio lists upcoming classes via the Events plugin, shows a weekly schedule collection, and provides event detail pages with registration links.

## Pages to demo

1. `/reference-projects/yoga/website/index.html` — `upcoming_events` template function
2. `/reference-projects/yoga/website/schedule.html` — class schedule collection
3. `/reference-projects/yoga/website/event.html?slug=sunrise-vinyasa` — event detail

## Talking points

- `data-birkly-prefetch` for `upcoming_events`
- `{event_register_url id}` for free RSVP
- Events collection schema from the plugin

## Prerequisites

```bash
./scripts/install-dev-plugins.sh
./scripts/install-reference-project.sh yoga
# Activate events plugin in Admin
```
