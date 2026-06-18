<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\AttendeeDTO;
use App\Models\Attendee;
use Illuminate\Support\Collection;

class AttendeeRepository
{
    public function __construct(
        private readonly Attendee $attendee,
    ) {}

    /**
     * Persist a new attendee and return its DTO.
     */
    public function create(AttendeeDTO $attendeeDTO): AttendeeDTO
    {
        $attendee = $this->attendee->create([
            'event_id' => $attendeeDTO->eventId,
            'name'     => $attendeeDTO->name,
            'email'    => $attendeeDTO->email,
        ]);

        return AttendeeDTO::fromModel($attendee);
    }

    /**
     * Return all attendees for an event.
     *
     * @return Collection<int, AttendeeDTO>
     */
    public function findByEvent(string $eventId): Collection
    {
        return $this->attendee
            ->where('event_id', '=', $eventId)
            ->get()
            ->map(fn (Attendee $attendee) => AttendeeDTO::fromModel($attendee));
    }

    /**
     * Check whether an email address is already registered for an event.
     */
    public function existsByEventAndEmail(string $eventId, string $email): bool
    {
        return $this->attendee
            ->where('event_id', '=', $eventId)
            ->where('email', '=', $email)
            ->exists();
    }

    /**
     * Return all attendees for events whose start time falls within a window.
     * Used by the reminder email scheduler.
     *
     * @return Collection<int, AttendeeDTO>
     */
    public function findByEventIds(array $eventIds): Collection
    {
        return $this->attendee
            ->whereIn('event_id', $eventIds)
            ->get()
            ->map(fn (Attendee $attendee) => AttendeeDTO::fromModel($attendee));
    }
}
