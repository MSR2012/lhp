# Event Visuals — Implementation Plan

> Reference document for the Event Visuals feature build.
> Architecture standards: see `ARCHITECTURE.md`. Agent guidelines: see `AGENTS.md`.

---

## Requirements

### Functional

| # | Requirement | Category |
|---|---|---|
| F1 | Two visually distinct event browsing pages (VisualOne, VisualTwo) | UI / Pages |
| F2 | Visual pages open in a new tab — no sidebar, no top bar | UI / Shell |
| F3 | Each event shows: title, description, location, date/time, images | UI / Display |
| F4 | Event images — at least 2 per event, served locally (no external URLs) | Data / Storage |
| F5 | Reverse-geocode lat/lng → human-readable address | Data / Enrichment |
| F6 | Display event times with sensible timezone handling | Data / Formatting |
| F7 | Filter by date and by location (minimum) | UI / Filtering |
| F8 | Register attendee interest for an event (attendee list) | Feature / Attendees |
| F9 | Confirmation email when attendee is added | Email / Jobs |
| F10 | Reminder email 3 days before event | Email / Scheduler |
| F11 | Reminder email 24 hours before event | Email / Scheduler |
| F12 | Tailwind CSS for all styling | Tech / Frontend |
| F13 | Animations where they make sense, not overdone | Tech / Frontend |

### Non-Functional

| # | Requirement | Category |
|---|---|---|
| NF1 | 1.25M+ events — all reads paginated and indexed | Performance |
| NF2 | Strict layered architecture: Controller → Service → Action/Repository | Architecture |
| NF3 | DTOs between all layers — no raw arrays or Request objects | Architecture |
| NF4 | Form Requests with `toDto()` for all write endpoints | Architecture |
| NF5 | `declare(strict_types=1)`, PSR-12 in every PHP file | Code Style |
| NF6 | All TypeScript — no `any`, all props typed via interfaces | Code Style |
| NF7 | Constructor injection — no `new`, no facades in business logic | Architecture |
| NF8 | Feature + unit tests, `@testdox` on all test methods | Testing |
| NF9 | Images served locally — no external hotlinks | Security |
| NF10 | No N+1 queries — eager load, use DB indexes | Performance |

---

## Architecture Decisions

### 1. New Tab + Chromeless Layout

Visual pages open in a new browser tab with no sidebar or header bar. This is solved at two independent layers:

**Layer 1 — Sidebar link behaviour**

`NavItem` type gains `target` and `external` fields. When `external: true`, `NavMain.vue` renders a plain `<a>` tag instead of Inertia's `<Link>`, forcing a real browser navigation to a new tab. Inertia's `<Link>` is bypassed entirely.

```
// resources/js/types/navigation.ts
NavItem {
    title: string
    href:  string
    icon?: LucideIcon
    isActive?: boolean
    target?:   '_blank' | '_self'   // NEW
    external?: boolean               // NEW — use <a>, not Inertia <Link>
}
```

`AppSidebar.vue` — mark both visual links:
```ts
{ title: 'Events Visual 1', href: '/events-visual-1', icon: BarChart3, target: '_blank', external: true }
{ title: 'Events Visual 2', href: '/events-visual-2', icon: Map,       target: '_blank', external: true }
```

**Layer 2 — Page layout opt-out**

`VisualLayout.vue` is a bare shell (no `AppShell`, no `AppSidebar`, no breadcrumbs). Each visual page sets `defineOptions({ layout: VisualLayout })` to override the default `AppSidebarLayout` that `app.ts` applies to all other pages.

`VisualLayout.vue` structure:
```
┌──────────────────────────────────────────────────────────────┐
│  [Logo → /dashboard]              [← Back to App]  [☀/☾]   │  sticky, h-12, backdrop-blur
├──────────────────────────────────────────────────────────────┤
│                        <slot />                              │
└──────────────────────────────────────────────────────────────┘
```
Inherits the same CSS custom properties (`--background`, `--foreground`, etc.) so light/dark mode works automatically. Includes `<Toaster />` for attendee registration feedback.

---

### 2. Offline Reverse Geocoding

#### Why offline?

Every event lat/lng was seeded by jittering ±0.5° around one of 65 known city anchors (`EventSeeder::CITY_ANCHORS`). An external geocoding API would introduce network latency, API key dependencies, and rate limits — none of which are needed because we already know every possible answer. The nearest-anchor approach is deterministic, instantaneous, and 100% accurate for the seeded dataset.

#### How it works — Haversine Nearest-Neighbour

The **Haversine formula** calculates the great-circle distance (shortest surface path) between two geographic coordinates:

```
Given event at (lat1, lon1) and anchor at (lat2, lon2):

  Δlat = lat2 - lat1  (converted to radians)
  Δlon = lon2 - lon1  (converted to radians)

  a = sin²(Δlat/2) + cos(lat1) × cos(lat2) × sin²(Δlon/2)
  c = 2 × atan2(√a, √(1 - a))
  d = 6371 × c          ← distance in km (6371 = Earth's radius)
```

We loop over all 65 anchors, compute `d` for each, and return the city label and IANA timezone of the anchor with the smallest `d`.

#### Why this is always correct for seeded data

Seeder jitter radius: ±0.5° ≈ 55 km maximum displacement.
Minimum distance between any two anchors: hundreds of km.

The nearest anchor is therefore always the correct one — no ambiguity is possible.

#### Implementation

**`app/Support/GeoAnchorMap.php`** — static data class, no dependencies:
```php
// Each entry: [lat, lng, city_label, iana_timezone]
// Mirrors EventSeeder::CITY_ANCHORS in the same index order
final class GeoAnchorMap
{
    public const ANCHORS = [
        [40.7128, -74.0060, 'New York, NY',        'America/New_York'],
        [34.0522, -118.2437, 'Los Angeles, CA',    'America/Los_Angeles'],
        // ... all 65 entries
    ];
}
```

**`app/Support/GeocoderService.php`** — pure service, constructor-injectable:
```php
public function resolve(float $lat, float $lng): array  // ['address' => ..., 'timezone' => ...]
{
    $nearest = null;
    $minDist = PHP_FLOAT_MAX;
    foreach (GeoAnchorMap::ANCHORS as [$aLat, $aLng, $city, $tz]) {
        $d = $this->haversine($lat, $lng, $aLat, $aLng);
        if ($d < $minDist) { $minDist = $d; $nearest = ['address' => $city, 'timezone' => $tz]; }
    }
    return $nearest;
}
```

#### Caching strategy

- Migration adds `address VARCHAR(500) NULL` and `timezone VARCHAR(64) NULL` to the `events` table
- `ResolveEventAddressAction` writes the result back to the row on first resolution
- Subsequent reads hit the column — Haversine is never re-computed for the same row
- An artisan command `events:resolve-addresses` pre-warms addresses in bulk (run once after seeding)

#### Timezone display on the frontend

Once `timezone` is stored (e.g. `"America/New_York"`), the Vue component uses the native `Intl.DateTimeFormat` API — no library needed:

```ts
function formatEventTime(unixTs: number, timezone: string): string {
    return new Intl.DateTimeFormat('en-US', {
        timeZone:     timezone,
        weekday:      'short',
        month:        'short',
        day:          'numeric',
        year:         'numeric',
        hour:         'numeric',
        minute:       '2-digit',
        timeZoneName: 'short',   // renders "EST", "GMT+1", etc.
    }).format(new Date(unixTs * 1000));
}
// → "Sat, Jun 21, 2025, 8:00 PM EST"
```

---

### 3. Reminder Email Windows

The scheduler runs daily. Events are matched using a ±1 hour window around the target offset to avoid races when the job fires slightly late:

| Reminder | Query window |
|---|---|
| 24 hours before | `created_time BETWEEN now()+23h AND now()+25h` |
| 3 days before | `created_time BETWEEN now()+71h AND now()+73h` |

---

## Database Changes

### Patch `events` table
```sql
ADD COLUMN address  VARCHAR(500) NULL
ADD COLUMN timezone VARCHAR(64)  NULL
CREATE INDEX events_created_time_idx ON events (created_time)
CREATE INDEX events_lat_lng_idx      ON events (latitude, longitude)
```

### New `event_images` table
```sql
id            CHAR(26) PRIMARY KEY          -- ULID
event_id      CHAR(36) FK → events.id CASCADE DELETE
path          VARCHAR(500) NOT NULL         -- e.g. "events/placeholder-1.jpg"
display_order TINYINT UNSIGNED DEFAULT 0
timestamps
INDEX (event_id)
```

### New `attendees` table
```sql
id         CHAR(26) PRIMARY KEY             -- ULID
event_id   CHAR(36) FK → events.id CASCADE DELETE
name       VARCHAR(255) NOT NULL
email      VARCHAR(255) NOT NULL
timestamps
UNIQUE INDEX (event_id, email)
INDEX (event_id)
```

---

## New Directory Structure

```
app/
├── Actions/
│   ├── RegisterAttendeeAction.php
│   └── ResolveEventAddressAction.php
├── Console/Commands/
│   └── ResolveEventAddresses.php            # php artisan events:resolve-addresses
├── DTOs/
│   ├── AttendeeDTO.php
│   ├── EventDTO.php
│   ├── EventFilterDTO.php
│   └── EventImageDTO.php
├── Exceptions/
│   └── DuplicateAttendeeException.php
├── Http/
│   ├── Controllers/
│   │   ├── AttendeeController.php
│   │   └── EventController.php              # add visualOne(), visualTwo()
│   └── Requests/
│       └── StoreAttendeeRequest.php
├── Jobs/
│   ├── SendAttendeeConfirmationEmail.php
│   └── SendEventReminderEmails.php
├── Mail/
│   ├── AttendeeConfirmation.php
│   └── EventReminder.php
├── Models/
│   ├── Attendee.php
│   ├── Event.php                            # add hasMany relationships + casts
│   └── EventImage.php
├── Repositories/
│   ├── AttendeeRepository.php
│   ├── EventImageRepository.php
│   └── EventRepository.php
├── Services/
│   ├── AttendeeService.php
│   └── EventService.php
└── Support/
    ├── GeoAnchorMap.php
    └── GeocoderService.php

database/migrations/
├── ..._patch_events_table.php
├── ..._create_event_images_table.php
└── ..._create_attendees_table.php

resources/
├── js/
│   ├── composables/
│   │   ├── useAttendee.ts
│   │   └── useEventFilters.ts
│   ├── layouts/
│   │   └── VisualLayout.vue
│   ├── pages/Events/
│   │   ├── VisualOne.vue                    # Card Grid
│   │   └── VisualTwo.vue                    # Timeline
│   └── types/
│       ├── attendee.ts
│       └── event.ts
└── views/mail/
    ├── attendee-confirmation.blade.php
    └── event-reminder.blade.php

public/images/events/
    event-1.jpg … event-8.jpg               # locally served placeholders
```

---

## Backend Layer Flows

### Visual page data
```
GET /events-visual-1?from=&to=&city=&status=&page=
  → EventController@visualOne(Request)
  → builds EventFilterDTO
  → EventService@getFilteredEvents(EventFilterDTO)
  → EventRepository@paginate(EventFilterDTO)
      WHERE created_time BETWEEN ? AND ?     (indexed)
      AND   address LIKE ?
      AND   status = ?                       (indexed)
      WITH  images (eager, limit 2)
      WITH  COUNT(attendees)
      PAGINATE 24
  → Collection<EventDTO>
  → Inertia::render('Events/VisualOne', [events, filters, ...])
```

### Attendee registration
```
POST /events/{event}/attendees
  → StoreAttendeeRequest (validates name, email)
  → AttendeeController@store(Event, StoreAttendeeRequest)
  → AttendeeService@register($request->toDto())
      → EventRepository@findOrFail()
      → AttendeeRepository@existsByEventAndEmail()  → throws DuplicateAttendeeException
      → RegisterAttendeeAction@execute(AttendeeDTO)
          → AttendeeRepository@create()
      → SendAttendeeConfirmationEmail::dispatch()   (queued)
  → JSON { success: true, attendee: AttendeeDTO }
```

### Reminder emails (daily scheduler)
```
Schedule::job(new SendEventReminderEmails('24hours'))->dailyAt('08:00')
Schedule::job(new SendEventReminderEmails('3days'))->dailyAt('08:00')

SendEventReminderEmails@handle():
  → EventRepository@findEventsStartingBetween(windowStart, windowEnd)
  → foreach event:
      AttendeeRepository@findByEvent(event->id)
      foreach attendee:
          Mail::to(attendee->email)->queue(new EventReminder($eventDto, $attendee, $window))
```

---

## Frontend Layouts

### VisualOne — Card Grid

**Concept:** Browse events like a festival lineup — bold cards, image-first.

- Responsive CSS grid: `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3`, gap-6
- Each card: hero image (crossfades to second image on hover), event name, type badge, venue, date/time in local timezone, city from `address`, attendee count, "Register" CTA
- Filter bar: sticky top, date range pickers + city text search + type chips
- Pagination: infinite scroll via `IntersectionObserver` (same pattern as `Events/Index.vue`)
- Animations: cards `animate-fade-up` staggered on load (`tw-animate-css`); hover `hover:-translate-y-1 transition-transform`
- Register UX: Reka UI `DialogRoot` modal — name + email fields, toast on success

### VisualTwo — Vertical Timeline

**Concept:** Chronological journey through events — like a newspaper "What's On" column.

- Events sorted by `created_time` ASC, grouped into month/year sections with date headers
- Vertical spine line with `●` connector dots; left-anchored layout on mobile, alternating on desktop
- Each item: image thumbnail left, title + venue + date/time/timezone + address + price right
- Filter UX: collapsible sidebar (desktop) / top drawer (mobile) — intentionally different from VisualOne
- Animations: each item `translate-x-8 opacity-0 → translate-x-0 opacity-100` on viewport entry (`useIntersectionObserver` from `@vueuse/core`); spine line `scaleY` grows as you scroll
- Register UX: inline expand below the event row (no modal — distinct from VisualOne by design)

---

## Parallel Task Breakdown

### Developer Tracks

| Track | Role | Tasks |
|---|---|---|
| **A** | Backend Infrastructure | T1, T2, T3, T4, T5, T16, T23 (partial) |
| **B** | Backend Domain | T6, T7, T8, T9, T10, T11, T12, T23 (partial) |
| **C** | Email & Scheduler | T13, T14, T15 |
| **D** | Frontend | T17, T18, T19, T20, T21 |
| **E** | QA / Tests | T22, T23 |

### Dependency Graph

```
Wave 0 — no blockers (all start Day 1)
  T1  Patch events migration ─────────────────────────────────────────┐
  T2  event_images migration ─────────────────────────────────────────┤
  T3  attendees migration ────────────────────────────────────────────┴──► T4
  T5  GeoAnchorMap + GeocoderService ────────────────────────────────────► T8, T10, T16
  T6  All DTOs ───────────────────────────────────────────────────────────► T7, T13, T14
  T17 TS types + composables ─────────────────────────────────────────────► T18, T19
  T20 VisualLayout + app.ts ──────────────────────────────────────────────► T18, T19
  T21 Placeholder images ─────────────────────────────────────────────────► T18, T19

Wave 1
  T4  Models ─────────────────────────────────────────────────────────────► T7

Wave 2
  T7  Repositories ───────────────────────────────────────────────────────► T8, T9, T10, T16

Wave 3 (T8 and T10 run in parallel)
  T8  Actions ────────────────────────────────────────────────────────────► T9, T16
  T10 EventService ───────────────────────────────────────────────────────► T11

Wave 3 (after T8)
  T9  AttendeeService ────────────────────────────────────────────────────► T12

Wave 4 (all unblocked by Wave 3; email side-branch starts from T6)
  T11 EventController refactor ───────────────────────────────────────────► T22
  T12 AttendeeController + route ─────────────────────────────────────────► T22
  T13 Jobs ──────────────────────────────────────────────────────────────► T15
  T14 Mailables ─────────────────────────────────────────────────────────► T15
  T15 Scheduler registration
  T16 Artisan command

Wave 5 (after T17 + T20)
  T18 VisualOne.vue ──────────────────────────────────────────────────────► T22
  T19 VisualTwo.vue ──────────────────────────────────────────────────────► T22

Wave 6 (after Waves 4 + 5)
  T22 Feature tests
  T23 Unit tests
```

---

### Task Cards

---

#### T1 — Patch events table migration
**Track A · Wave 0 · Day 1**
**Depends on:** nothing
**Blocks:** T4

**Output:** `database/migrations/YYYY_MM_DD_000001_patch_events_table.php`
- `$table->string('address', 500)->nullable()`
- `$table->string('timezone', 64)->nullable()`
- `$table->index('created_time')`
- `$table->index(['latitude', 'longitude'])`

---

#### T2 — Create event_images migration
**Track A · Wave 0 · Day 1**
**Depends on:** nothing
**Blocks:** T4

**Output:** `database/migrations/YYYY_MM_DD_000002_create_event_images_table.php`
- `ulid('id')->primary()`
- `uuid('event_id')->constrained()->cascadeOnDelete()`
- `string('path', 500)`
- `unsignedTinyInteger('display_order')->default(0)`
- `timestamps()`

---

#### T3 — Create attendees migration
**Track A · Wave 0 · Day 1**
**Depends on:** nothing
**Blocks:** T4

**Output:** `database/migrations/YYYY_MM_DD_000003_create_attendees_table.php`
- `ulid('id')->primary()`
- `uuid('event_id')->constrained()->cascadeOnDelete()`
- `string('name')`, `string('email')`
- `unique(['event_id', 'email'])`
- `timestamps()`

---

#### T4 — Models: EventImage, Attendee, update Event
**Track A · Wave 1 · Day 1 pm**
**Depends on:** T1, T2, T3
**Blocks:** T7

**Outputs:**
- `app/Models/EventImage.php` — `HasUlids`, `belongsTo(Event::class)`, `display_order` cast to int
- `app/Models/Attendee.php` — `HasUlids`, `belongsTo(Event::class)`
- Updated `app/Models/Event.php` — add `hasMany(EventImage::class)`, `hasMany(Attendee::class)`, add `address` and `timezone` to `$casts`

---

#### T5 — GeoAnchorMap + GeocoderService
**Track A · Wave 0 · Day 1**
**Depends on:** nothing
**Blocks:** T8, T10, T16

**Input:** `database/seeders/EventSeeder.php` — `CITY_ANCHORS` array (65 entries to annotate)

**Outputs:**
- `app/Support/GeoAnchorMap.php` — `final class`, `const ANCHORS` array, each entry `[lat, lng, city_label, iana_timezone]`, same index order as seeder
- `app/Support/GeocoderService.php` — `resolve(float $lat, float $lng): array`, Haversine nearest-neighbour, constructor-injectable, no facades, no external calls

---

#### T6 — All DTOs
**Track B · Wave 0 · Day 1**
**Depends on:** nothing
**Blocks:** T7, T13, T14

**Outputs** (all in `app/DTOs/`):
- `EventDTO.php` — `id, userId, type, status, createdTime, latitude, longitude, address, timezone, payload (array), images (EventImageDTO[])`
- `EventFilterDTO.php` — `status, dateFrom, dateTo, city`
- `AttendeeDTO.php` — `id, eventId, name, email`
- `EventImageDTO.php` — `id, eventId, path, displayOrder`
- Each DTO: `readonly` properties, `static fromModel(Model): static` factory, `declare(strict_types=1)`

---

#### T7 — All Repositories
**Track B · Wave 2 · Day 2**
**Depends on:** T4 (models), T6 (DTOs)
**Blocks:** T8, T9, T10, T16

**Outputs** (all in `app/Repositories/`):
- `EventRepository.php`
  - `findById(string $id): EventDTO`
  - `findOrFail(string $id): EventDTO`
  - `paginate(EventFilterDTO $filter, int $perPage = 24): LengthAwarePaginator`
  - `updateAddressAndTimezone(string $id, string $address, string $timezone): void`
  - `findWithoutAddress(int $limit): Collection`
  - `findEventsStartingBetween(Carbon $from, Carbon $to): Collection`
- `AttendeeRepository.php`
  - `create(AttendeeDTO): AttendeeDTO`
  - `findByEvent(string $eventId): Collection`
  - `existsByEventAndEmail(string $eventId, string $email): bool`
- `EventImageRepository.php`
  - `create(EventImageDTO): EventImageDTO`
  - `findByEvent(string $eventId): Collection`

⚠️ `EventRepository::paginate()` must only filter on indexed columns (`created_time`, `status`, `address`). No full-table scans.

---

#### T8 — Actions
**Track B · Wave 3 · Day 2–3**
**Depends on:** T7 (repositories), T5 (GeocoderService)
**Blocks:** T9, T16

**Outputs** (in `app/Actions/`):
- `ResolveEventAddressAction.php` — injects `GeocoderService` + `EventRepository`; reads lat/lng, calls `geocoder->resolve()`, calls `repository->updateAddressAndTimezone()`
- `RegisterAttendeeAction.php` — injects `AttendeeRepository`; calls `create()`, returns `AttendeeDTO`; does NOT dispatch email

---

#### T9 — AttendeeService
**Track B · Wave 3 · Day 3**
**Depends on:** T7 (repositories), T8 (RegisterAttendeeAction)
**Blocks:** T12

**Outputs:**
- `app/Services/AttendeeService.php` — `register(AttendeeDTO): AttendeeDTO`
  1. `EventRepository::findOrFail()` — verify event exists
  2. `AttendeeRepository::existsByEventAndEmail()` — throw `DuplicateAttendeeException` if true
  3. `RegisterAttendeeAction::execute($dto)` — persist
  4. `SendAttendeeConfirmationEmail::dispatch($attendeeDto)` — queue email
  5. Return DTO
- `app/Exceptions/DuplicateAttendeeException.php`

---

#### T10 — EventService
**Track B · Wave 3 · Day 3**
**Depends on:** T7 (repositories), T5 (GeocoderService)
**Blocks:** T11

**Note:** Runs in parallel with T9.

**Output:**
- `app/Services/EventService.php`
  - `getFilteredEvents(EventFilterDTO): LengthAwarePaginator`
  - `getEventWithImages(string $id): EventDTO`
  - `resolveAddressIfMissing(EventDTO): EventDTO` — on-demand fallback before bulk pre-warm

---

#### T11 — Refactor EventController + routes
**Track B · Wave 4 · Day 3**
**Depends on:** T10 (EventService)
**Blocks:** T22

⚠️ Existing `index()`, `data()`, `show()` methods must remain unchanged.

**Outputs:**
- Updated `app/Http/Controllers/EventController.php`
  - Add constructor injection of `EventService`
  - Add `visualOne(Request): Response` — `EventService::getFilteredEvents()` → `Inertia::render('Events/VisualOne', [...])`
  - Add `visualTwo(Request): Response` — same service, different Inertia component
- Updated `routes/web.php`
  - Replace `Route::inertia('events-visual-1', ...)` with `[EventController::class, 'visualOne']`
  - Replace `Route::inertia('events-visual-2', ...)` with `[EventController::class, 'visualTwo']`

---

#### T12 — AttendeeController + StoreAttendeeRequest + route
**Track B · Wave 4 · Day 3**
**Depends on:** T9 (AttendeeService)
**Blocks:** T22

**Outputs:**
- `app/Http/Controllers/AttendeeController.php` — `store(StoreAttendeeRequest, Event): JsonResponse`; calls `AttendeeService::register()`
- `app/Http/Requests/StoreAttendeeRequest.php` — validates `name` (required, max 255), `email` (required, email, max 255); `toDto(): AttendeeDTO` with `eventId` from route model binding
- Updated `routes/web.php` — `Route::post('events/{event}/attendees', [AttendeeController::class, 'store'])->name('events.attendees.store')`

---

#### T13 — Jobs
**Track C · Wave 4 · Day 2**
**Depends on:** T6 (DTOs only — no service needed to write the class)
**Blocks:** T15

**Outputs:**
- `app/Jobs/SendAttendeeConfirmationEmail.php` — `ShouldQueue`; constructor `(AttendeeDTO $attendee, EventDTO $event)`; `handle()` sends `AttendeeConfirmation` mailable
- `app/Jobs/SendEventReminderEmails.php` — `ShouldQueue`; constructor `(string $window: '24hours'|'3days')`; `handle()` queries `AttendeeRepository`, dispatches `EventReminder` per attendee

---

#### T14 — Mailables + Blade views
**Track C · Wave 4 · Day 2**
**Depends on:** T6 (DTOs)
**Blocks:** T15

**Note:** Runs in parallel with T13.

**Outputs:**
- `app/Mail/AttendeeConfirmation.php` — `Mailable`; `envelope()` sets subject; `content()` uses Blade view
- `app/Mail/EventReminder.php` — `Mailable`; accepts `AttendeeDTO`, `EventDTO`, `string $window`
- `resources/views/mail/attendee-confirmation.blade.php`
- `resources/views/mail/event-reminder.blade.php`

---

#### T15 — Scheduler registration
**Track C · Wave 4 · Day 3**
**Depends on:** T13

**Output:** Updated `routes/console.php`:
```php
Schedule::job(new SendEventReminderEmails('24hours'))->dailyAt('08:00');
Schedule::job(new SendEventReminderEmails('3days'))->dailyAt('08:00');
```

---

#### T16 — Artisan command `events:resolve-addresses`
**Track A · Wave 4 · Day 3**
**Depends on:** T5 (GeocoderService), T7 (EventRepository), T8 (ResolveEventAddressAction)

**Output:** `app/Console/Commands/ResolveEventAddresses.php`
- Signature: `events:resolve-addresses {--limit=500} {--chunk=50}`
- Fetches events where `address IS NULL` in chunks
- Calls `ResolveEventAddressAction::execute()` per event
- Idempotent — re-runnable safely (only processes null rows)
- Progress bar + final count output

---

#### T17 — TypeScript types + composables
**Track D · Wave 0 · Day 1**
**Depends on:** nothing
**Blocks:** T18, T19

**Inputs:** `EventSeeder.php` payload structure, `resources/js/composables/useCurrentUrl.ts` (pattern)

**Outputs:**
- `resources/js/types/event.ts` — `EventImage`, `EventPayload`, `EventRow` (full shape including `address`, `timezone`, `images`, nested payload fields — no `any`)
- `resources/js/types/attendee.ts` — `Attendee`, `AttendeeForm`
- `resources/js/composables/useAttendee.ts` — `submitAttendee(eventId, form)`, reactive `loading`, `error`, `success`
- `resources/js/composables/useEventFilters.ts` — reactive `status`, `dateFrom`, `dateTo`, `city`; `applyFilters()`, `reset()`

---

#### T18 — VisualOne.vue (Card Grid)
**Track D · Wave 5 · Day 2**
**Depends on:** T17, T20, T21
**Blocks:** T22

**Output:** `resources/js/pages/Events/VisualOne.vue` (replaces empty stub)
- `defineOptions({ layout: VisualLayout })`
- Responsive grid `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3`
- Card: hero image (crossfades to second on hover), title, type badge, venue, date via `Intl.DateTimeFormat` + `timezone`, city from `address`, attendee count, "Register" button
- Sticky filter bar: date range + city text input + type chips, wired to `useEventFilters`
- Infinite scroll: `IntersectionObserver` (same pattern as `Events/Index.vue`)
- Card entry animation: `animate-fade-up` with staggered `animation-delay` via `tw-animate-css`
- Register UX: Reka UI `DialogRoot` modal, wired to `useAttendee`, toast on success

---

#### T19 — VisualTwo.vue (Timeline)
**Track D · Wave 5 · Day 2**
**Depends on:** T17, T20, T21
**Blocks:** T22

**Note:** Different developer from T18 if possible.

**Output:** `resources/js/pages/Events/VisualTwo.vue` (replaces empty stub)
- `defineOptions({ layout: VisualLayout })`
- Events sorted by `created_time` ASC, grouped by month/year with date-header sections
- Vertical spine line + `●` connector dots; left-anchored on mobile, alternating on desktop
- Each item: thumbnail, title, venue + formatted date/timezone, address, price, attendee count
- Filter UX: collapsible sidebar (desktop) / top drawer (mobile) — distinct from VisualOne's top bar
- Scroll-reveal animation: `translate-x-8 opacity-0 → translate-x-0 opacity-100` per item via `useIntersectionObserver` (`@vueuse/core`)
- Register UX: inline expand below the event row — no modal (intentionally different from VisualOne)

---

#### T20 — VisualLayout.vue + app.ts
**Track D · Wave 0 · Day 1**
**Depends on:** nothing
**Blocks:** T18, T19

**Inputs:** `resources/js/layouts/app/AppSidebarLayout.vue` (pattern), `resources/js/app.ts`

**Outputs:**
- `resources/js/layouts/VisualLayout.vue` — bare shell: sticky minimal nav (logo + back link + dark toggle), `<slot />`, `<Toaster />`, no `AppShell`/`AppSidebar`
- Updated `resources/js/app.ts` — layout resolver maps `Events/VisualOne` and `Events/VisualTwo` to `VisualLayout`
- Updated `resources/js/types/navigation.ts` — add `target?: '_blank' | '_self'` and `external?: boolean` to `NavItem`
- Updated `resources/js/components/NavMain.vue` — render `<a :href :target>` when `item.external === true`, else `<Link>`
- Updated `resources/js/components/AppSidebar.vue` — add `target: '_blank', external: true` to both visual nav entries

---

#### T21 — Placeholder images
**Track D · Wave 0 · Day 1**
**Depends on:** nothing
**Blocks:** T18, T19

**Output:** `public/images/events/` directory with 6–8 files (`event-1.jpg` … `event-8.jpg`), each < 100 KB, varied subject matter (venues, crowds, stages, etc.), served as static assets with no external references.

---

#### T22 — Feature tests
**Track E · Wave 6 · Day 4**
**Depends on:** T11, T12, T18, T19

**Outputs** (in `tests/Feature/`):
- `AttendeeRegistrationTest.php`
  - `@testdox` on every test
  - Covers: successful registration, duplicate email → 422, missing fields → 422, `Queue::fake()` asserts `SendAttendeeConfirmationEmail` dispatched, event not found → 404
- `EventVisualPageTest.php`
  - Covers: both visual routes return 200, correct Inertia component names, filter props present in page, `address` and `images` present in paginated data

---

#### T23 — Unit tests
**Track E (or split with Track A/B) · Wave 6 · Day 3**
**Depends on:** T5, T7, T9

**Outputs** (in `tests/Unit/`):
- `GeocoderServiceTest.php` — Haversine accuracy with known coordinate pairs, correct timezone per region, exact anchor match edge case
- `AttendeeServiceTest.php` — mock repos + action; duplicate throws `DuplicateAttendeeException`; happy path dispatches job; returns `AttendeeDTO`
- `EventRepositoryTest.php` — `RefreshDatabase` with small factory dataset; date range filter, status filter, `findWithoutAddress` returns only null-address rows

---

## Summary Table

| Task | Track | Wave | Earliest Start | Blocks |
|---|---|---|---|---|
| T1  Patch events migration | A | 0 | Day 1 | T4 |
| T2  event_images migration | A | 0 | Day 1 | T4 |
| T3  attendees migration | A | 0 | Day 1 | T4 |
| T5  GeoAnchorMap + GeocoderService | A | 0 | Day 1 | T8, T10, T16 |
| T6  All DTOs | B | 0 | Day 1 | T7, T13, T14 |
| T17 TS types + composables | D | 0 | Day 1 | T18, T19 |
| T20 VisualLayout + app.ts + nav changes | D | 0 | Day 1 | T18, T19 |
| T21 Placeholder images | D | 0 | Day 1 | T18, T19 |
| T4  Models | A | 1 | Day 1 pm | T7 |
| T7  All Repositories | B | 2 | Day 2 | T8, T9, T10, T16 |
| T8  Actions | B | 3 | Day 2–3 | T9, T16 |
| T9  AttendeeService | B | 3 | Day 3 | T12 |
| T10 EventService | B | 3 | Day 3 | T11 |
| T13 Jobs | C | 4 | Day 2* | T15 |
| T14 Mailables + Blade views | C | 4 | Day 2* | T15 |
| T11 EventController refactor | B | 4 | Day 3 | T22 |
| T12 AttendeeController + route | B | 4 | Day 3 | T22 |
| T15 Scheduler registration | C | 4 | Day 3 | — |
| T16 Artisan command | A | 4 | Day 3 | — |
| T18 VisualOne.vue (Card Grid) | D | 5 | Day 2 | T22 |
| T19 VisualTwo.vue (Timeline) | D | 5 | Day 2 | T22 |
| T22 Feature tests | E | 6 | Day 4 | — |
| T23 Unit tests | E | 6 | Day 3 | — |

*T13/T14 only need T6 (DTOs) — Track C can start Day 2.

---

## Critical Path

```
T1+T2+T3 → T4 → T6 → T7 → T8+T9 → T10 → T11 → T22
```

**~3.5–4 working days.** Frontend (T17→T20→T18/T19) and email (T13→T14→T15) both complete within that window and do not extend it.
