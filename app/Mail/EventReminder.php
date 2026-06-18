<?php

declare(strict_types=1);

namespace App\Mail;

use App\DTOs\AttendeeDTO;
use App\DTOs\EventDTO;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EventReminder extends Mailable
{
    public function __construct(
        public readonly AttendeeDTO $attendee,
        public readonly EventDTO $event,
        public readonly string $window,
    ) {}

    public function envelope(): Envelope
    {
        $when = $this->window === '24hours' ? 'tomorrow' : 'in 3 days';

        return new Envelope(
            subject: 'Reminder: ' . ($this->event->payload['name'] ?? 'Your event') . ' is ' . $when,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.event-reminder',
        );
    }
}
