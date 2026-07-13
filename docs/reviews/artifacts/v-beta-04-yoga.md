# V-BETA-04 — Yoga: enroll in an event

**Journey:** J-YOGA · **Date:** 2026-07-13

## Prerequisites

```bash
./scripts/prepare-reference-demo.sh yoga
php -S localhost:8080 -t . server-router.php
```

## Browser steps (recorded)

1. Open `/reference-projects/yoga/website/index.html`
2. Wait for **Stillwater Yoga** events list (not coffee products)
3. Click **Details & register** on **Sunrise Vinyasa**
4. Verify event detail: title, date, description, RSVP form
5. Fill **Your name** and **Email**
6. Click **Complete registration**
7. Page reloads with cleared form (success)

## Fixes applied

- `event.html` RSVP form (`data-birkly-route="events"`, `action=rsvp`)
- `plugins/events/api/events.php` reads `$_POST` for browser forms
- Wrong slug `community-meetup` → `sunrise-vinyasa`

## Automated gate

```bash
php scripts/journey-runner.php --journey J-YOGA
```

Asserts: upcoming events, RSVP API returns registration.

## Video

`v-beta-blog-yoga-interaction-demos.mp4` (yoga segment)
