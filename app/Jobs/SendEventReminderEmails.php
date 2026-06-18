<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\AttendeeDTO;
use App\DTOs\EventDTO;
use App\Mail\EventReminder;
use App\Repositories\AttendeeRepository;
use App\Repositories\EventRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendEventReminderEmails implements ShouldQueue
{
    use Queueable;

    /**
     * @param string $window '24hours' or '3days'
     */
    public function __construct(
        private readonly string $window,
    ) {}

    public function handle(EventRepository $eventRepository, AttendeeRepository $attendeeRepository): void
    {
        $now = time();

        [$fromOffset, $toOffset] = match ($this->window) {
            '24hours' => [23 * 3600, 25 * 3600],
            '3days'   => [71 * 3600, 73 * 3600],
        };

        $events = $eventRepository->findEventsStartingBetween(
            $now + $fromOffset,
            $now + $toOffset,
        );

        $eventIds = $events->map(fn (EventDTO $eventDTO) => $eventDTO->id)->all();

        if (empty($eventIds)) {
            return;
        }

        $attendeesByEvent = $attendeeRepository->findByEventIds($eventIds)
            ->groupBy(fn (AttendeeDTO $attendeeDTO) => $attendeeDTO->eventId);

        foreach ($events as $eventDTO) {
            $eventAttendees = $attendeesByEvent->get($eventDTO->id, collect());

            foreach ($eventAttendees as $attendeeDTO) {
                Mail::to($attendeeDTO->email)
                    ->queue(new EventReminder($attendeeDTO, $eventDTO, $this->window));
            }
        }
    }
}
