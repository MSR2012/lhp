# Architecture & Coding Standards

> Single source of truth for this project. Written at project start. Follow this — don't improvise.

---

## Stack

- **PHP 8.3** · Laravel 13 · Inertia.js · Vue 3 + TypeScript · Tailwind v4
- **Database:** MySQL 8.4
- **Queue:** Laravel database queue (emails, reminders)
- **Scheduler:** Laravel scheduler (reminder jobs)

---

## Layer Architecture

Every feature follows this strict flow — no shortcuts:

```
Request → Controller → Service → Action (writes) → Repository → Model
                              ↘ Repository (reads)
```

| Layer | Responsibility | Location |
|---|---|---|
| **Controller** | Receive request, delegate, return response. No logic. | `app/Http/Controllers/` |
| **Service** | Orchestrate business logic. Reads from Repository directly. Writes only through Actions. | `app/Services/` |
| **Action** | Single write operation. Injects Repository to persist. One action per operation. | `app/Actions/` |
| **Repository** | All DB access — reads and writes. The only layer that touches the Model. | `app/Repositories/` |
| **Model** | Schema definition, casts, relationships only. No business logic. | `app/Models/` |
| **DTO** | Typed data transfer between all layers. No raw arrays or Request objects passed between layers. | `app/DTOs/` |

### Rules
- Controllers never touch the DB or Service layer logic — delegate only
- Services read directly from Repository, but **never write directly** — all writes go through Actions
- Actions own a single write responsibility and call Repository to persist
- Repository is the **only** layer that interacts with Models
- All communication between layers uses DTOs — never arrays, never Request objects
- All dependencies injected via constructor — never instantiated with `new` inside a class, never resolved via facades or `app()` helper inside business logic
- All validation handled in Form Request classes — never in controllers or services
- Custom validation logic extracted into dedicated Rule classes (`app/Rules/`), never inlined as closures

---

## Directory Structure

```
app/
├── Actions/          # One class per write operation
├── DTOs/             # Typed data objects
├── Http/
│   ├── Controllers/  # Thin — delegate only
│   └── Requests/     # Form validation + toDto() method
├── Rules/            # Custom validation rules
├── Models/           # Eloquent models
├── Repositories/     # Read queries
├── Services/         # Business logic orchestration
└── Support/          # Stateless library/utility classes (no DB, no orchestration)
```

---

## Naming Conventions

| Thing | Convention | Example |
|---|---|---|
| Classes | `UpperCamelCase` | `EventService` |
| Methods | `lowerCamelCase` | `registerAttendee()` |
| Variables | `lowerCamelCase`, self-explanatory | `$confirmedAttendees` |
| Constants | `UPPER_SNAKE_CASE` | `MAX_ATTENDEES` |
| Actions | Verb + Noun + `Action` | `CreateAttendeeAction` |
| Services | Noun + `Service` | `AttendeeService` |
| Repositories | Noun + `Repository` | `EventRepository` |
| DTOs | Noun + `DTO` | `AttendeeDTO` |
| Interfaces | Noun + `Interface` | `EventRepositoryInterface` |
| Form Requests | Verb + Noun + `Request` | `StoreAttendeeRequest` |

---

## Code Style

- PSR-12 formatting (enforced by Laravel Pint)
- Strict types: `declare(strict_types=1)` in every PHP file
- Type-hint everything — parameters, return types, properties
- Strict comparisons only (`===`, `!==`)
- Single quotes for strings; `sprintf()` for interpolation
- No `@` error suppression
- One class per file

### Form Requests must implement `toDto()`

```php
class StoreAttendeeRequest extends FormRequest
{
    public function toDto(): AttendeeDTO
    {
        return new AttendeeDTO(
            name: $this->validated('name'),
            email: $this->validated('email'),
        );
    }
}
```

### Actions inject Repository to persist

```php
class CreateAttendeeAction
{
    public function __construct(
        private readonly AttendeeRepository $attendeeRepository,
    ) {}

    public function execute(AttendeeDTO $dto): AttendeeDTO
    {
        return $this->attendeeRepository->create($dto);
    }
}
```

### Services read from Repository, write through Actions

```php
class AttendeeService
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly CreateAttendeeAction $createAttendee,
    ) {}

    public function register(AttendeeDTO $dto): AttendeeDTO
    {
        // Read directly from repository
        $event = $this->eventRepository->findOrFail($dto->eventId);

        // Write only through action → repository
        $attendee = $this->createAttendee->execute($dto);

        // dispatch mail, etc.
        return $attendee;
    }
}
```

### Repository handles all DB access

```php
class AttendeeRepository
{
    public function __construct(
        private readonly Attendee $attendee,
    ) {}

    public function create(AttendeeDTO $dto): AttendeeDTO
    {
        $attendee = $this->attendee->create([
            'event_id' => $dto->eventId,
            'name'     => $dto->name,
            'email'    => $dto->email,
        ]);

        return AttendeeDTO::fromModel($attendee);
    }

    public function findByEvent(string $eventId): Collection
    {
        return $this->attendee->where('event_id', $eventId)->get()
            ->map(fn ($attendee) => AttendeeDTO::fromModel($attendee));
    }
}
```

---

## PHPDoc

Document all classes, properties, and methods:

```php
/**
 * Registers a new attendee for an event and dispatches confirmation email.
 *
 * @param AttendeeDTO $dto
 * @return Attendee
 */
public function register(AttendeeDTO $dto): Attendee
```

---

## Frontend (Vue 3 + TypeScript)

- All props typed via TypeScript interfaces — no `any`
- Composables for reusable logic (`useFilters`, `useAttendee`)
- Tailwind only for styling — no inline styles
- Animations where they aid UX; none for decoration only

---

## Git

- Commit messages are descriptive and in imperative mood: `Add attendee registration`, `Fix timezone offset on event cards`
- Never commit without a message
- One logical change per commit
