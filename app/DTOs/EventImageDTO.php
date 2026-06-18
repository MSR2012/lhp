<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\EventImage;

final class EventImageDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $eventId,
        public readonly string $path,
        public readonly int $displayOrder,
    ) {}

    public static function fromModel(EventImage $eventImage): self
    {
        return new self(
            id: $eventImage->id,
            eventId: $eventImage->event_id,
            path: $eventImage->path,
            displayOrder: $eventImage->display_order,
        );
    }
}
