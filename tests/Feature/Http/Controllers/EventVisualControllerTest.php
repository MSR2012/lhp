<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\EventImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @testdox EventController visual pages
 */
describe('EventController visual pages', function () {

    /**
     * @testdox visual one renders the correct Inertia component
     */
    it('visual one renders the correct Inertia component', function () {
        $this->get(route('events.visual1'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Events/VisualOne'));
    });

    /**
     * @testdox visual two renders the correct Inertia component
     */
    it('visual two renders the correct Inertia component', function () {
        $this->get(route('events.visual2'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Events/VisualTwo'));
    });

    /**
     * @testdox visual pages pass events, filters and statuses as props
     */
    it('passes events, filters, statuses and cities as props', function () {
        $this->get(route('events.visual1'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/VisualOne')
                ->has('events')
                ->has('filters')
                ->has('statuses')
                ->has('cities')
            );
    });

    /**
     * @testdox visual one returns only published events when filtered by status
     */
    it('visual one returns only published events when filtered by status', function () {
        $user = User::factory()->create();
        Event::factory()->for($user)->count(3)->create(['status' => 'published']);
        Event::factory()->for($user)->count(2)->create(['status' => 'cancelled']);

        $this->get(route('events.visual1', ['status' => 'published']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/VisualOne')
                ->where('events.total', 3)
            );
    });

    /**
     * @testdox visual one returns events filtered by city address
     */
    it('visual one returns events filtered by city address', function () {
        $user = User::factory()->create();
        Event::factory()->for($user)->count(2)->create(['address' => 'New York, NY', 'status' => 'published']);
        Event::factory()->for($user)->count(3)->create(['address' => 'London, UK', 'status' => 'published']);

        $this->get(route('events.visual1', ['city' => 'New York']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('events.total', 2)
            );
    });

    /**
     * @testdox visual one returns events with their images eager loaded
     */
    it('visual one returns events with their images eager loaded', function () {
        $user  = User::factory()->create();
        $event = Event::factory()->for($user)->create(['status' => 'published']);
        EventImage::factory()->for($event)->count(2)->create();

        $this->get(route('events.visual1'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Events/VisualOne')
                ->has('events.data.0.images')
            );
    });

    /**
     * @testdox visual two returns only events filtered by date range
     */
    it('visual two returns only events filtered by date range', function () {
        $user = User::factory()->create();

        // inside range: 2024-06-01
        Event::factory()->for($user)->create(['created_time' => strtotime('2024-06-01'), 'status' => 'published']);
        // outside range: 2023-01-01
        Event::factory()->for($user)->create(['created_time' => strtotime('2023-01-01'), 'status' => 'published']);

        $this->get(route('events.visual2', ['from' => '2024-01-01', 'to' => '2024-12-31']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('events.total', 1)
            );
    });
});
