<?php

declare(strict_types=1);

namespace App\Actions;

use App\Repositories\EventRepository;
use App\Support\GeocoderService;

/**
 * Resolves and persists the human-readable address and timezone for a single
 * event using offline Haversine nearest-neighbour geocoding.
 *
 * Idempotent — safe to call on an event that already has an address.
 */
class ResolveEventAddressAction
{
    public function __construct(
        private readonly GeocoderService $geocoderService,
        private readonly EventRepository $eventRepository,
    ) {}

    /**
     * Resolve the address for the given event ID and persist it.
     */
    public function execute(string $eventId): void
    {
        $eventDTO = $this->eventRepository->findOrFail($eventId);

        if ($eventDTO->latitude === null || $eventDTO->longitude === null) {
            return;
        }

        $resolved = $this->geocoderService->resolve($eventDTO->latitude, $eventDTO->longitude);

        $this->eventRepository->updateAddressAndTimezone(
            $eventId,
            $resolved['address'],
            $resolved['timezone'],
        );
    }
}
