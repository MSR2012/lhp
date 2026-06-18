<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Attendee;

final class AttendeeDTO
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $eventId,
        public readonly string $name,
        public readonly string $email,
    ) {}

    public static function fromModel(Attendee $attendee): self
    {
        return new self(
            id: $attendee->id,
            eventId: $attendee->event_id,
            name: $attendee->name,
            email: $attendee->email,
        );
    }
}
