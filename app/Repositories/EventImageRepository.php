<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\EventImageDTO;
use App\Models\EventImage;
use Illuminate\Support\Collection;

class EventImageRepository
{
    public function __construct(
        private readonly EventImage $eventImage,
    ) {}

    /**
     * Persist a new event image and return its DTO.
     */
    public function create(EventImageDTO $eventImageDTO): EventImageDTO
    {
        $eventImage = $this->eventImage->create([
            'event_id'      => $eventImageDTO->eventId,
            'path'          => $eventImageDTO->path,
            'display_order' => $eventImageDTO->displayOrder,
        ]);

        return EventImageDTO::fromModel($eventImage);
    }

    /**
     * Return all images for an event ordered by display_order.
     *
     * @return Collection<int, EventImageDTO>
     */
    public function findByEvent(string $eventId): Collection
    {
        return $this->eventImage
            ->where('event_id', '=', $eventId)
            ->orderBy('display_order')
            ->get()
            ->map(fn (EventImage $eventImage) => EventImageDTO::fromModel($eventImage));
    }
}
