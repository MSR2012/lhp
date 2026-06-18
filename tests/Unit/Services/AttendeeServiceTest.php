<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Actions\RegisterAttendeeAction;
use App\DTOs\AttendeeDTO;
use App\Exceptions\DuplicateAttendeeException;
use App\Models\Event;
use App\Models\User;
use App\Repositories\AttendeeRepository;
use App\Repositories\EventRepository;
use App\Services\AttendeeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * @testdox AttendeeService
 */
describe('AttendeeService', function () {

    beforeEach(function () {
        $this->eventRepository     = new EventRepository(new Event());
        $this->attendeeRepository  = new AttendeeRepository(new \App\Models\Attendee());
        $this->registerAction      = new RegisterAttendeeAction($this->attendeeRepository);
        $this->service             = new AttendeeService(
            $this->eventRepository,
            $this->attendeeRepository,
            $this->registerAction,
        );
    });

    /**
     * @testdox registers an attendee and returns a persisted DTO with an ID
     */
    it('registers an attendee and returns a persisted DTO with an id', function () {
        $event = Event::factory()->for(User::factory()->create())->create();

        $attendeeDTO = new AttendeeDTO(
            id: null,
            eventId: $event->id,
            name: 'Jane Doe',
            email: 'jane@example.com',
        );

        $result = $this->service->register($attendeeDTO);

        expect($result->id)->not->toBeNull()
            ->and($result->name)->toBe('Jane Doe')
            ->and($result->email)->toBe('jane@example.com')
            ->and($result->eventId)->toBe($event->id);
    });

    /**
     * @testdox throws DuplicateAttendeeException when the same email registers twice
     */
    it('throws DuplicateAttendeeException on duplicate registration', function () {
        $event = Event::factory()->for(User::factory()->create())->create();

        $attendeeDTO = new AttendeeDTO(
            id: null,
            eventId: $event->id,
            name: 'Jane Doe',
            email: 'jane@example.com',
        );

        $this->service->register($attendeeDTO);

        expect(fn () => $this->service->register($attendeeDTO))
            ->toThrow(DuplicateAttendeeException::class);
    });

    /**
     * @testdox throws ModelNotFoundException when the event does not exist
     */
    it('throws ModelNotFoundException when the event does not exist', function () {
        $attendeeDTO = new AttendeeDTO(
            id: null,
            eventId: '00000000-0000-0000-0000-000000000000',
            name: 'Jane Doe',
            email: 'jane@example.com',
        );

        expect(fn () => $this->service->register($attendeeDTO))
            ->toThrow(ModelNotFoundException::class);
    });

    /**
     * @testdox does not persist the attendee when the event does not exist
     */
    it('does not persist the attendee when the event does not exist', function () {
        $attendeeDTO = new AttendeeDTO(
            id: null,
            eventId: '00000000-0000-0000-0000-000000000000',
            name: 'Jane Doe',
            email: 'jane@example.com',
        );

        try {
            $this->service->register($attendeeDTO);
        } catch (ModelNotFoundException) {
        }

        expect(\App\Models\Attendee::count())->toBe(0);
    });
});
