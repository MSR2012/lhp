# LHP Coding Test

Laravel 13 + Vue 3 + Inertia.js event-management application with attendee registration, geocoding, email notifications, and two visual event browsers.

## Stack

- **Backend**: PHP 8.3, Laravel 13, MySQL
- **Frontend**: Vue 3 (Composition API), TypeScript, Inertia.js, Tailwind CSS v4
- **Queue**: Laravel queue (database driver by default)
- **Mail**: Laravel Mailable (configure driver in `.env`)

---

## Setup

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
php artisan db:seed          # seeds events, images, attendees
npm run build
```

To run the development server:

```bash
php artisan serve &
npm run dev
```

---

## Artisan Commands

### `events:resolve-addresses`

Geocodes events that have no resolved address by converting stored lat/lng coordinates into a human-readable address and IANA timezone via the nearest-neighbour lookup in `GeoAnchorMap`.

```bash
php artisan events:resolve-addresses
```

| Option | Default | Description |
|--------|---------|-------------|
| `--limit` | `500` | Maximum number of events to process in one run |
| `--chunk` | `50` | Number of events fetched per database batch |

Example — process up to 1 000 events in batches of 100:

```bash
php artisan events:resolve-addresses --limit=1000 --chunk=100
```

### `events:seed-images`

Assigns two placeholder images to every event that currently has none. The command is **idempotent** — running it multiple times will not create duplicate images. Images are drawn round-robin from `public/images/events/event-1.jpg` … `event-8.jpg`.

```bash
php artisan events:seed-images
```

| Option | Default | Description |
|--------|---------|-------------|
| `--chunk` | `2000` | Number of events processed per database batch |

Example — smaller batches on memory-constrained environments:

```bash
php artisan events:seed-images --chunk=500
```

---

## Jobs

Jobs live in `app/Jobs/` and implement `ShouldQueue` so they are processed asynchronously by a queue worker.

### `SendAttendeeConfirmationEmail`

Dispatched automatically by `AttendeeService` whenever a new attendee successfully registers for an event. Sends an HTML confirmation email (view: `mail.attendee-confirmation`) containing the event name, venue, address, and date/time.

**Triggered by**: `POST /events/{event}/attendees`

### `SendEventReminderEmails`

Queries events whose start time falls within a rolling time window and queues one `EventReminder` mailable per attendee for each matching event.

Supported windows:

| Window | Looks for events starting in |
|--------|------------------------------|
| `24hours` | 23 – 25 hours from now |
| `3days` | 71 – 73 hours from now |

**Triggered by**: the scheduler (see below). Can also be dispatched manually:

```bash
php artisan tinker
>>> \App\Jobs\SendEventReminderEmails::dispatchSync('24hours');
```

---

## Mailables

| Mailable | View | Subject |
|----------|------|---------|
| `AttendeeConfirmation` | `mail.attendee-confirmation` | You're registered! {event name} |
| `EventReminder` | `mail.event-reminder` | Reminder: {event name} is tomorrow / in 3 days |

Configure the mail driver in `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=mailpit          # or your SMTP host
MAIL_PORT=1025
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

---

## Scheduler

Two reminder jobs run **daily at 08:00** (server time):

```
08:00  SendEventReminderEmails('24hours')  — notifies attendees of events starting tomorrow
08:00  SendEventReminderEmails('3days')    — notifies attendees of events starting in 3 days
```

Defined in `routes/console.php`. To activate the scheduler, add one cron entry to the server:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

To run the scheduler locally:

```bash
php artisan schedule:work
```

---

## Queue Worker

Start a worker to process queued jobs (confirmation emails, reminder emails):

```bash
php artisan queue:work
```

For production, use a process supervisor (e.g. Supervisor):

```ini
[program:laravel-worker]
command=php /path/to/project/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
```

---

## Running Tests

```bash
php artisan test
```

Or in parallel:

```bash
php artisan test --parallel
```

The test suite covers HTTP controllers, service layer, repositories, and architecture constraints.

---

## Event Visual Pages

Two read-only public pages for browsing events. Both pages exclude `draft` and `cancelled` events (only `published` and `sold_out` events are shown).

| Route | Description |
|-------|-------------|
| `/events-visual/one` | Card grid with hover image crossfade and infinite scroll |
| `/events-visual/two` | Chronological timeline grouped by month with scroll-reveal |

Both pages support the same filters: **status**, **city** (type-ahead backed by 65 anchor cities), **date from**, **date to**.

The city filter matches on both the stored address text and a ±0.6° lat/lng bounding box, so events whose addresses have not yet been geocoded are still discoverable by city.

Pagination data is loaded via a shared JSON endpoint:

```
GET /events-visual/data?status=&city=&dateFrom=&dateTo=&page=2
```
