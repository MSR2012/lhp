<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Event;
use Illuminate\Support\Collection;

final class EventDTO
{
    /**
     * @param array<string, mixed>  $payload
     * @param EventImageDTO[]       $images
     */
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $type,
        public readonly string $status,
        public readonly ?int $createdTime,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly ?string $address,
        public readonly ?string $timezone,
        public readonly array $payload,
        public readonly array $images,
        public readonly int $attendeesCount = 0,
    ) {}

    public static function fromModel(Event $event): self
    {
        $images = Collection::make($event->images ?? [])
            ->map(fn ($eventImage) => EventImageDTO::fromModel($eventImage))
            ->all();

        return new self(
            id: $event->id,
            userId: (string) $event->user_id,
            type: $event->type,
            status: $event->status,
            createdTime: $event->created_time,
            latitude: $event->latitude,
            longitude: $event->longitude,
            address: $event->address,
            timezone: $event->timezone,
            payload: $event->payload ?? [],
            images: $images,
            attendeesCount: (int) ($event->attendees_count ?? 0),
        );
    }
}
