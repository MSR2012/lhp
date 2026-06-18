<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\AttendeeDTO;
use App\DTOs\EventDTO;
use App\Mail\AttendeeConfirmation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendAttendeeConfirmationEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly AttendeeDTO $attendeeDTO,
        private readonly EventDTO $eventDTO,
    ) {}

    public function handle(): void
    {
        Mail::to($this->attendeeDTO->email)
            ->send(new AttendeeConfirmation($this->attendeeDTO, $this->eventDTO));
    }
}
