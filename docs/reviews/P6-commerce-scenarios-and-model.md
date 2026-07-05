# Commerce — scenarios, content model & lean architecture

**Status:** Design discussion (2026-07-05) — refine before lead-engineer handoff  
**Parent:** `docs/reviews/P6-commerce-phase2-master-plan.md`

---

## Core model (two independent axes)

Commerce behavior splits into **two axes that must not be coupled**:

| Axis | Question | Values |
|------|----------|--------|
| **Fulfillment** | What happens after payment? | `ship` · `deliver` · `register` · `none` |
| **Purchase options** | How can the customer pay? | One or more of `{ billing, price, interval, label }` |

A single entry can expose **both** one-time and recurring purchase options (e.g. coffee bag OR monthly subscription). Fulfillment (`ship`) applies regardless of which option the buyer chose.

```json
{
  "fulfillment": "ship",
  "purchase_options": [
    {
      "id": "buy_once",
      "label": "One-time purchase",
      "billing": "one_time",
      "price_cents": 1499,
      "currency": "eur"
    },
    {
      "id": "subscribe",
      "label": "Subscribe & save",
      "billing": "recurring",
      "interval": "month",
      "price_cents": 1299,
      "currency": "eur"
    }
  ],
  "quantity_available": null,
  "sku": null
}
```

Checkout: buyer picks **purchase option** (and variant if any) → shelf charges the selected price/billing mode.

**Lean rule:** No `offer_type` enum that implies billing. UI presets (“Physical product”, “Digital download”, “Event ticket”) only **set default fulfillment + suggested fields** — merchant can override.

---

## Variants — content model (elegant Birkly-native)

**Problem:** Size, grind, color modify price/SKU/stock but share one marketing page.

**Solution:** Parent entry = story; **sub-collection `variants`** = sellable SKUs.

```
Collection: products
├── Entry: "Ethiopian Single Origin"     ← hero content, images, body
│   └── sub-collection: variants
│       ├── Sub-entry: 250g · Whole bean   ← commerce field (price, sku, qty)
│       ├── Sub-entry: 250g · Ground
│       ├── Sub-entry: 1kg · Whole bean
│       └── Sub-entry: 1kg · Ground
```

| Layer | Holds |
|-------|--------|
| **Parent entry** | Title, ContentEditor, gallery, SEO, optional default purchase_options |
| **Variant sub-entry** | Attribute fields (size, grind), **commerce field** (price override, SKU, stock), optional purchase_options override |
| **Catalog index** | One row per **sellable unit** (variant sub-entry if variants exist, else parent) |
| **Templates** | List variants from parent; `{commerce_buy_url}` targets variant sub-entry id |

**Why sub-entries, not a matrix field?**

- Each variant is real content (can have its own slug, image, status)
- AI/MCP can read/write variants like any entry
- Import CSV maps 1 row = 1 variant sub-entry
- No bespoke “variant engine” — sub-collections already exist

**Parent without variants:** Commerce field on parent only (simple product).

**Variant modifies price:** Commerce field on sub-entry; parent has no price or shows “from €X” via template function `commerce_price_from parent`.

---

## Scenario 1 — Sell a yoga class (Event / `register`)

**Business:** Single workshop, 20 spots, €25, one date, optional free intro call.

### Birkly setup

| Step | Action |
|------|--------|
| 1 | **Collection** `classes` — fields: title, description (ContentEditor), image, `when` (datetime), `location` (text), **`pricing` (commerce)** |
| 2 | **Commerce field on entry** — `fulfillment: register`, `purchase_options: [{ one_time, €25 }]`, `quantity_available: 20` |
| 3 | **Entry** “Sunday Morning Vinyasa” — fill schedule + pricing |
| 4 | **Activate** Commerce plugin + payment provider (Stripe/Mollie) |
| 5 | **Purchase mapping** (optional) — none, or grant tier `yoga-member` if bundled later |
| 6 | **Automation** — trigger `commerce_event_registered` → confirmation email with date/location |
| 7 | **Client template** — class detail page: description, `{when}`, `{if pricing.quantity_available > 0}` buy button `{commerce_buy_url entry.slug classes 1}` |

### Checkout flow (customer)

1. Clicks buy on class page  
2. Checkout collects email + attendee name (because `fulfillment: register`)  
3. Pays €25 via shelf  
4. **Post-purchase:** registration record, capacity 20→19, email sent  

### Admin flow

1. Commerce → Catalog — see class, low capacity warning  
2. Commerce → Orders — paid order with attendee  
3. Entry editor — quantity reflects remaining spots (synced from index)  

**No Events plugin required** for new sites — legacy Events plugin optional for old `events` collection.

---

## Scenario 2 — Sell coffee (Physical / `ship` + dual purchase options)

**Business:** Beans in sizes/grinds; buy once or subscribe monthly; ships from warehouse.

### Birkly setup

| Step | Action |
|------|--------|
| 1 | **Collection** `products` — sub-collection **`variants`** with fields: `size` (select), `grind` (select), **`pricing` (commerce)** |
| 2 | **Parent entry** “Ethiopian Single Origin” — marketing copy, photos, no commerce field OR “from” display only |
| 3 | **Variant sub-entries** (4 combos) — each: size + grind + commerce: |
| | • `fulfillment: ship` |
| | • `purchase_options: [ { one_time, €14.99 }, { recurring/month, €12.99 } ]` |
| | • `quantity_available`, `sku` per variant |
| 4 | **Shipping** (Wave 2) — zones + flat rate in Commerce settings |
| 5 | **Fulfillment** — ShipStation or manual export on `commerce_physical_fulfilled` |
| 6 | **Client template** — product page loops variants (sub-entries); buyer picks size/grind → sees two prices “Buy once” / “Subscribe” |

### Checkout flow

1. Select variant (250g whole bean)  
2. Select purchase option (subscribe monthly)  
3. Enter shipping address (`fulfillment: ship`)  
4. Pay €12.99 — recurring mandate via provider  
5. **Post-purchase:** order line snapshot includes variant attrs + subscription id; fulfillment queue for warehouse pick/pack  

### Why dual purchase options matter here

Same variant, same fulfillment — **two checkout prices**. Merchant does not duplicate entries or collections.

---

## Scenario 3 — Tutorials + community (Digital / `deliver` + membership / recurring)

**Business:** Video course (buy once) + optional community (monthly); or community-only signup.

**Two patterns — both valid:**

### Pattern A — Two entries (clearest content model)

| Entry | Collection | Fulfillment | Purchase options |
|-------|------------|-------------|------------------|
| “Complete Lighting Course” | `courses` | `deliver` | one_time €89 |
| “Creator Community” | `memberships` | `none` | recurring/month €9 |

| Step | Action |
|------|--------|
| 1 | Course entry — commerce deliver; link media vault or external Vimeo + `delivery_mode: secure_download` or `external_url` |
| 2 | Community entry — commerce none + recurring; **purchase mapping** → grant tier `community` |
| 3 | User plugin — tier gates community template pages |
| 4 | Automation — `commerce_digital_delivered` → email with course link; `commerce_membership_started` → welcome email |
| 5 | **Landing page** — two cards, two buy URLs |

### Pattern B — One entry, two purchase options (your coffee-style flexibility)

Single entry “Lighting Course + Community”:

```json
{
  "fulfillment": "deliver",
  "purchase_options": [
    { "id": "course_only", "billing": "one_time", "price_cents": 8900, "label": "Course only" },
    { "id": "course_plus_community", "billing": "recurring", "interval": "month", "price_cents": 900,
      "label": "Course + Community", "includes": ["tier:community", "asset:course-123"] }
  ]
}
```

Post-purchase router reads selected `purchase_option_id`:

- `course_only` → digital delivery handler only  
- `course_plus_community` → digital delivery + tier grant  

**Lean recommendation:** Pattern A for v1 (two entries = simpler router). Pattern B when purchase-option metadata (`includes[]`) lands in Wave 2.

### Checkout flow (Pattern A — course)

1. Buy course → email  
2. Pay €89  
3. Signed download / member area link  
4. Automation adds user to “purchased courses” list (custom field on user or tier)

### Checkout flow (Pattern A — community)

1. Buy community → account required (User plugin)  
2. Pay €9/mo recurring  
3. Tier `community` granted → access to forum template  

---

## Scenario map → architecture

| Scenario | Fulfillment | Purchase options | Variants | Post-purchase |
|----------|-------------|------------------|----------|---------------|
| Yoga class | `register` | one_time | — | Registration + capacity |
| Coffee | `ship` | one_time **+** recurring | sub-entry variants | Fulfillment push |
| Course | `deliver` | one_time | — | Download / email |
| Community | `none` | recurring | — | Tier grant |
| Course + community (advanced) | `deliver` + mapping | both on one entry (later) | — | deliver + tier |

---

## Lean implementation order (revised)

| Wave | Scope |
|------|--------|
| **1** | `purchase_options[]` + `fulfillment`; fieldtype getConfigUI; catalog/checkout picks option id |
| **2a** | Variants via sub-collection convention + indexer |
| **2b** | Three fulfillment handlers: register, deliver, ship (minimal viable each) |
| **3** | Purchase option `includes` for combo offers; bundles deferred |

**Not in Wave 1:** separate session type, handler registry, bundle fieldtype, tax engine.

---

## Handoff constraints (for lead engineer)

1. **Decouple** fulfillment from billing — `purchase_options` array is required for Wave 1 schema.  
2. **Variants** = sub-collection pattern documented, not inline matrix v1.  
3. **Three scenarios** are acceptance fixtures — tests/docs reference yoga, coffee, course.  
4. **Pattern A** (two entries) before Pattern B (combo purchase_options) for tutorials+community.
