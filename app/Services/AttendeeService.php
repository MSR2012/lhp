<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\RegisterAttendeeAction;
use App\DTOs\AttendeeDTO;
use App\Exceptions\DuplicateAttendeeException;
use App\Jobs\SendAttendeeConfirmationEmail;
use App\Repositories\AttendeeRepository;
use App\Repositories\EventRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AttendeeService
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly AttendeeRepository $attendeeRepository,
        private readonly RegisterAttendeeAction $registerAttendeeAction,
    ) {}

    /**
     * Register an attendee for an event.
     *
     * Verifies the event exists, guards against duplicate registration,
     * persists the attendee, and dispatches a confirmation email.
     *
     * @throws ModelNotFoundException      If the event does not exist.
     * @throws DuplicateAttendeeException  If the email is already registered.
     */
    public function register(AttendeeDTO $attendeeDTO): AttendeeDTO
    {
        $eventDTO = $this->eventRepository->findOrFail($attendeeDTO->eventId);

        if ($this->attendeeRepository->existsByEventAndEmail($attendeeDTO->eventId, $attendeeDTO->email)) {
            throw new DuplicateAttendeeException($attendeeDTO->email, $attendeeDTO->eventId);
        }

        $persistedAttendee = $this->registerAttendeeAction->execute($attendeeDTO);

        SendAttendeeConfirmationEmail::dispatch($persistedAttendee, $eventDTO);

        return $persistedAttendee;
    }
}
