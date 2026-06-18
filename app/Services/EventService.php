<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\ResolveEventAddressAction;
use App\DTOs\EventDTO;
use App\DTOs\EventFilterDTO;
use App\Repositories\EventRepository;
use App\Support\GeoAnchorMap;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EventService
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly ResolveEventAddressAction $resolveEventAddressAction,
    ) {}

    /**
     * Return all known city labels derived from the geo anchor map, sorted alphabetically.
     *
     * @return string[]
     */
    public function getCities(): array
    {
        $cities = array_column(GeoAnchorMap::ANCHORS, 2);
        sort($cities);
        return $cities;
    }

    /**
     * Return a paginated list of events applying the given filters.
     *
     * @return LengthAwarePaginator<EventDTO>
     */
    public function getFilteredEvents(EventFilterDTO $eventFilterDTO, int $perPage = 24): LengthAwarePaginator
    {
        return $this->eventRepository->paginate($eventFilterDTO, $perPage);
    }

    /**
     * Return a single event with its images loaded.
     * Resolves the address on-demand if it has not been geocoded yet.
     */
    public function getEventWithImages(string $id): EventDTO
    {
        $eventDTO = $this->eventRepository->findOrFail($id);

        return $this->resolveAddressIfMissing($eventDTO);
    }

    /**
     * Resolve and persist the address for an event if it is not yet set.
     * Returns the updated DTO.
     */
    public function resolveAddressIfMissing(EventDTO $eventDTO): EventDTO
    {
        if ($eventDTO->address !== null) {
            return $eventDTO;
        }

        $this->resolveEventAddressAction->execute($eventDTO->id);

        return $this->eventRepository->findOrFail($eventDTO->id);
    }
}
