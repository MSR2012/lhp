<?php

declare(strict_types=1);

namespace App\Mail;

use App\DTOs\AttendeeDTO;
use App\DTOs\EventDTO;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AttendeeConfirmation extends Mailable
{
    public function __construct(
        public readonly AttendeeDTO $attendee,
        public readonly EventDTO $event,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You\'re registered! ' . ($this->event->payload['name'] ?? 'Event'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.attendee-confirmation',
        );
    }
}
