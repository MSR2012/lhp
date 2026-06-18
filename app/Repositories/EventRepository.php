<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\EventDTO;
use App\DTOs\EventFilterDTO;
use App\Models\Event;
use App\Support\GeoAnchorMap;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class EventRepository
{
    public function __construct(
        private readonly Event $event,
    ) {}

    /**
     * Find a single event by ID, returning null if not found.
     */
    public function findById(string $id): ?EventDTO
    {
        $event = $this->event
            ->with('images')
            ->find($id);

        return $event ? EventDTO::fromModel($event) : null;
    }

    /**
     * Find a single event by ID or throw if not found.
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(string $id): EventDTO
    {
        $event = $this->event
            ->with('images')
            ->findOrFail($id);

        return EventDTO::fromModel($event);
    }

    /**
     * Return a paginated list of events applying the given filters.
     * Only uses indexed columns in WHERE clauses (status, created_time, address).
     *
     * @return LengthAwarePaginator<EventDTO>
     */
    public function paginate(EventFilterDTO $eventFilterDTO, int $perPage = 24): LengthAwarePaginator
    {
        $query = $this->event
            ->with(['images' => fn ($q) => $q->orderBy('display_order')->limit(2)])
            ->withCount('attendees');

        if ($eventFilterDTO->allowedStatuses !== null) {
            $query->whereIn('status', $eventFilterDTO->allowedStatuses);
        }

        if ($eventFilterDTO->status !== null) {
            $query->where('status', '=', $eventFilterDTO->status);
        }

        if ($eventFilterDTO->dateFrom !== null) {
            $query->where('created_time', '>=', strtotime($eventFilterDTO->dateFrom));
        }

        if ($eventFilterDTO->dateTo !== null) {
            $query->where('created_time', '<=', strtotime($eventFilterDTO->dateTo));
        }

        if ($eventFilterDTO->city !== null) {
            $anchor = GeoAnchorMap::findAnchorByCity($eventFilterDTO->city);

            $query->where(function ($q) use ($eventFilterDTO, $anchor) {
                $q->where('address', 'like', sprintf('%%%s%%', $eventFilterDTO->city));

                if ($anchor !== null) {
                    // ±0.6° bounding box (~67 km) covers the ±0.5° seeder jitter
                    $q->orWhere(function ($q2) use ($anchor) {
                        $q2->whereBetween('latitude',  [$anchor[0] - 0.6, $anchor[0] + 0.6])
                           ->whereBetween('longitude', [$anchor[1] - 0.6, $anchor[1] + 0.6]);
                    });
                }
            });
        }

        return $query
            ->orderByDesc('created_time')
            ->paginate($perPage)
            ->through(fn (Event $event) => EventDTO::fromModel($event));
    }

    /**
     * Write the resolved address and timezone back to the event row.
     */
    public function updateAddressAndTimezone(string $id, string $address, string $timezone): void
    {
        $this->event
            ->where('id', '=', $id)
            ->update([
                'address'  => $address,
                'timezone' => $timezone,
            ]);
    }

    /**
     * Return events that have not yet been geocoded, up to the given limit.
     *
     * @return Collection<int, EventDTO>
     */
    public function findWithoutAddress(int $limit): Collection
    {
        return $this->event
            ->whereNull('address')
            ->limit($limit)
            ->get()
            ->map(fn (Event $event) => EventDTO::fromModel($event));
    }

    /**
     * Return events whose start time falls within the given Unix timestamp window.
     * Used by the reminder email scheduler.
     *
     * @return Collection<int, EventDTO>
     */
    public function findEventsStartingBetween(int $fromTimestamp, int $toTimestamp): Collection
    {
        return $this->event
            ->where('created_time', '>=', $fromTimestamp)
            ->where('created_time', '<=', $toTimestamp)
            ->where('status', '=', 'published')
            ->get()
            ->map(fn (Event $event) => EventDTO::fromModel($event));
    }
}
