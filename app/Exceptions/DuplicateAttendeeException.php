<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class DuplicateAttendeeException extends RuntimeException
{
    public function __construct(string $email, string $eventId)
    {
        parent::__construct(
            sprintf('"%s" is already registered for event %s.', $email, $eventId),
        );
    }
}
