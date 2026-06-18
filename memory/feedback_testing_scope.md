---
name: Testing scope preference
description: What to test and what not to test in this project
type: feedback
---

Do NOT write tests for models or repositories.

Only write tests for:
- Business logic: services, actions, and any support classes that contain logic (e.g. GeocoderService)
- Controller endpoints: feature tests (no mocks — hit real DB via RefreshDatabase)

**Why:** The human made this explicit when model tests were proposed. Testing models and repositories adds noise without value; the meaningful coverage comes from business logic and HTTP integration.

**How to apply:** Before writing any test, ask "does this file contain business logic or is it an HTTP endpoint?" If no to both, skip it.

Test folder structure must mirror source folder structure exactly:
- `app/Support/GeocoderService.php` → `tests/Unit/Support/GeocoderServiceTest.php`
- `app/Services/AttendeeService.php` → `tests/Unit/Services/AttendeeServiceTest.php`
- `app/Http/Controllers/AttendeeController.php` → `tests/Feature/Http/Controllers/AttendeeControllerTest.php`
