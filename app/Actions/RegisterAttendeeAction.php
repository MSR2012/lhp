<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\AttendeeDTO;
use App\Repositories\AttendeeRepository;

/**
 * Persists a new attendee record for an event.
 * Does not dispatch emails — that is the Service's responsibility.
 */
class RegisterAttendeeAction
{
    public function __construct(
        private readonly AttendeeRepository $attendeeRepository,
    ) {}

    /**
     * Execute the action and return the persisted attendee DTO.
     */
    public function execute(AttendeeDTO $attendeeDTO): AttendeeDTO
    {
        return $this->attendeeRepository->create($attendeeDTO);
    }
}
