# Event fieldtype

Add an **event** field to any collection schema. The Events plugin indexes all entries with this field into `events_index.json` for calendar views and feeds.

## Schedule

- `starts_at` / `ends_at` — ISO datetime
- `timezone` — IANA timezone (e.g. `Europe/Berlin`)
- `recurrence` — optional series (v1):
  - `frequency`: `daily`, `weekly`, or `monthly`
  - `interval`: every N periods
  - `until` (end date) **or** `count` (max occurrences) — one required when recurrence is enabled
  - `skip_dates[]` — cancelled single dates (`YYYY-MM-DD`)

## Location

- `venue`, `virtual_url`, `hybrid` flag

## Capacity & Commerce

- `quantity_available` — free RSVP cap (when not using Commerce)
- `commerce_field` — name of a Commerce field on the same entry with `fulfillment: register` for paid tickets

## Calendar export

- Per-event ICS: `event_ics_url(collection, entry_id, occurrence_index)`
- Subscribe feed: `events_calendar_feed_url()` — works with Google Calendar and Apple Calendar (`webcal://`)

## Legacy

The legacy `events` collection and plugin registrations continue to work alongside fieldtype-based events.
