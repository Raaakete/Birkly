# V-BETA-04 — Yoga studio events

**Date:** 2026-07-13 · **Phase:** P28-M17  
**Journey:** J-YOGA

Events visitor browses upcoming yoga classes using events plugin.

## Prerequisites

- Birkly dev server running
- Events plugin installed: `./scripts/install-dev-plugins.sh events`
- Yoga reference project: `./scripts/install-reference-project.sh yoga`

## Steps

1. **Yoga studio homepage**
   - Navigate to `/reference-projects/yoga/website/index.html`
   - Hero section with studio mission
   - "Upcoming Classes" section
   - Events loaded via `upcoming_events` template function
   - Shows next 6 events by default

2. **Schedule page**
   - `/reference-projects/yoga/website/schedule.html`
   - Full weekly class schedule
   - Events grouped by date
   - Each event shows: time, instructor, level, duration

3. **Event detail page**
   - `/reference-projects/yoga/website/event.html?slug=community-meetup`
   - Event title, description, full details
   - Date/time, location information
   - Instructor bio and photo
   - Registration CTA button

4. **API verification**
   - `page_bundle` with `template_function` type
   - Function name: `upcoming_events`, args: `[6]`
   - Returns array of upcoming event entries
   - Filtered by `event_date >= now()`
   - Sorted by `event_date` ascending

## Automated verification

```bash
./scripts/install-dev-plugins.sh events
./scripts/install-reference-project.sh yoga
./scripts/journey-runner.php --journey J-YOGA
```

Journey verifies homepage references `upcoming_events`, schedule and detail pages load, page_bundle returns upcoming events array.

## Status

✅ Automated journey passes (J-YOGA)
