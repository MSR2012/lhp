<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Actions\ResolveEventAddressAction;
use App\Models\Event;
use App\Models\User;
use App\Repositories\EventRepository;
use App\Services\EventService;
use App\Support\GeocoderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * @testdox EventService
 */
describe('EventService', function () {

    beforeEach(function () {
        $eventRepository              = new EventRepository(new Event());
        $geocoderService              = new GeocoderService();
        $resolveEventAddressAction    = new ResolveEventAddressAction($geocoderService, $eventRepository);

        $this->eventRepository = $eventRepository;
        $this->service         = new EventService($eventRepository, $resolveEventAddressAction);
    });

    /**
     * @testdox returns paginated events respecting the per-page limit
     */
    it('returns paginated events respecting the per-page limit', function () {
        $user = User::factory()->create();
        Event::factory()->for($user)->count(5)->create(['status' => 'published']);

        $filterDTO = new \App\DTOs\EventFilterDTO(
            status: null,
            dateFrom: null,
            dateTo: null,
            city: null,
        );

        $paginator = $this->service->getFilteredEvents($filterDTO, perPage: 3);

        expect($paginator->total())->toBe(5)
            ->and($paginator->count())->toBe(3);
    });

    /**
     * @testdox filters events by status
     */
    it('filters events by status', function () {
        $user = User::factory()->create();
        Event::factory()->for($user)->count(3)->create(['status' => 'published']);
        Event::factory()->for($user)->count(2)->create(['status' => 'cancelled']);

        $filterDTO = new \App\DTOs\EventFilterDTO(
            status: 'cancelled',
            dateFrom: null,
            dateTo: null,
            city: null,
        );

        $paginator = $this->service->getFilteredEvents($filterDTO);

        expect($paginator->total())->toBe(2);
    });

    /**
     * @testdox resolves and persists the address when it is missing
     */
    it('resolves and persists the address when it is missing', function () {
        $user  = User::factory()->create();
        $event = Event::factory()->for($user)->create([
            'latitude'  => 40.7128,
            'longitude' => -74.0060,
            'address'   => null,
            'timezone'  => null,
        ]);

        $eventDTO = $this->service->getEventWithImages($event->id);

        expect($eventDTO->address)->toBe('New York, NY')
            ->and($eventDTO->timezone)->toBe('America/New_York');
    });

    /**
     * @testdox does not re-resolve the address when it is already set
     */
    it('does not re-resolve the address when it is already set', function () {
        $user  = User::factory()->create();
        $event = Event::factory()->for($user)->create([
            'latitude'  => 40.7128,
            'longitude' => -74.0060,
            'address'   => 'Already Set City',
            'timezone'  => 'America/New_York',
        ]);

        $eventDTO = $this->service->getEventWithImages($event->id);

        expect($eventDTO->address)->toBe('Already Set City');
    });
});
