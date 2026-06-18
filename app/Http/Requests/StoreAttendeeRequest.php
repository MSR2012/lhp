<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\AttendeeDTO;
use App\Models\Event;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreAttendeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    /**
     * This is a JSON-only endpoint — always return 422 JSON, never a redirect.
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json(
                ['message' => 'The given data was invalid.', 'errors' => $validator->errors()],
                422,
            )
        );
    }

    /**
     * Build an AttendeeDTO from validated input and the route-bound event.
     */
    public function toDto(): AttendeeDTO
    {
        /** @var Event $event */
        $event = $this->route('event');

        return new AttendeeDTO(
            id: null,
            eventId: $event->id,
            name: $this->validated('name'),
            email: $this->validated('email'),
        );
    }
}
