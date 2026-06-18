<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\DuplicateAttendeeException;
use App\Http\Requests\StoreAttendeeRequest;
use App\Models\Event;
use App\Services\AttendeeService;
use Illuminate\Http\JsonResponse;

class AttendeeController extends Controller
{
    public function __construct(
        private readonly AttendeeService $attendeeService,
    ) {}

    /**
     * Register an attendee for the given event.
     *
     * Returns 201 on success, 409 if the email is already registered,
     * or 422 if validation fails (handled automatically by FormRequest).
     */
    public function store(StoreAttendeeRequest $storeAttendeeRequest, Event $event): JsonResponse
    {
        try {
            $attendeeDTO = $this->attendeeService->register($storeAttendeeRequest->toDto());
        } catch (DuplicateAttendeeException $duplicateAttendeeException) {
            return response()->json(
                ['message' => $duplicateAttendeeException->getMessage()],
                409,
            );
        }

        return response()->json(['attendee' => $attendeeDTO], 201);
    }
}
