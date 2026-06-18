<?php

declare(strict_types=1);

use App\Models\Attendee;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @testdox registers an attendee and returns 201 with the attendee data
 */
it('registers an attendee and returns 201 with the attendee data', function () {
    $event = Event::factory()->for(User::factory()->create())->create();

    $this->postJson(route('events.attendees.store', $event), [
        'name'  => 'Jane Doe',
        'email' => 'jane@example.com',
    ])
        ->assertStatus(201)
        ->assertJsonPath('attendee.name', 'Jane Doe')
        ->assertJsonPath('attendee.email', 'jane@example.com')
        ->assertJsonPath('attendee.eventId', $event->id);
});

/**
 * @testdox persists the attendee to the database
 */
it('persists the attendee to the database', function () {
    $event = Event::factory()->for(User::factory()->create())->create();

    $this->postJson(route('events.attendees.store', $event), [
        'name'  => 'Jane Doe',
        'email' => 'jane@example.com',
    ])->assertStatus(201);

    expect(
        Attendee::where('event_id', $event->id)->where('email', 'jane@example.com')->exists()
    )->toBeTrue();
});

/**
 * @testdox returns 409 when the same email registers for the same event twice
 */
it('returns 409 when the same email registers for the same event twice', function () {
    $event   = Event::factory()->for(User::factory()->create())->create();
    $payload = ['name' => 'Jane Doe', 'email' => 'jane@example.com'];

    $this->postJson(route('events.attendees.store', $event), $payload)->assertStatus(201);
    $this->postJson(route('events.attendees.store', $event), $payload)->assertStatus(409);
});

/**
 * @testdox allows the same email to register for a different event
 */
it('allows the same email to register for a different event', function () {
    $user   = User::factory()->create();
    $eventA = Event::factory()->for($user)->create();
    $eventB = Event::factory()->for($user)->create();

    $payload = ['name' => 'Jane Doe', 'email' => 'jane@example.com'];

    $this->postJson(route('events.attendees.store', $eventA), $payload)->assertStatus(201);
    $this->postJson(route('events.attendees.store', $eventB), $payload)->assertStatus(201);
});

/**
 * @testdox returns 422 when name is missing
 */
it('returns 422 when name is missing', function () {
    $event    = Event::factory()->for(User::factory()->create())->create();
    $response = $this->postJson(route('events.attendees.store', $event), ['email' => 'jane@example.com']);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
});

/**
 * @testdox returns 422 when email is missing
 */
it('returns 422 when email is missing', function () {
    $event    = Event::factory()->for(User::factory()->create())->create();
    $response = $this->postJson(route('events.attendees.store', $event), ['name' => 'Jane Doe']);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

/**
 * @testdox returns 422 when email is not a valid email address
 */
it('returns 422 when email is not a valid email address', function () {
    $event    = Event::factory()->for(User::factory()->create())->create();
    $response = $this->postJson(route('events.attendees.store', $event), [
        'name'  => 'Jane Doe',
        'email' => 'not-an-email',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

/**
 * @testdox returns 404 when the event does not exist
 */
it('returns 404 when the event does not exist', function () {
    $this->postJson(route('events.attendees.store', '00000000-0000-0000-0000-000000000000'), [
        'name'  => 'Jane Doe',
        'email' => 'jane@example.com',
    ])->assertStatus(404);
});
