import { ref } from 'vue';
import type { Attendee, AttendeeForm } from '@/types/attendee';

export interface UseAttendeeReturn {
    loading: ReturnType<typeof ref<boolean>>;
    error: ReturnType<typeof ref<string | null>>;
    success: ReturnType<typeof ref<boolean>>;
    submitAttendee: (eventId: string, form: AttendeeForm) => Promise<Attendee | null>;
    reset: () => void;
}

export function useAttendee(): UseAttendeeReturn {
    const loading = ref(false);
    const error = ref<string | null>(null);
    const success = ref(false);

    async function submitAttendee(eventId: string, form: AttendeeForm): Promise<Attendee | null> {
        loading.value = true;
        error.value = null;
        success.value = false;

        try {
            const response = await fetch(`/events/${eventId}/attendees`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify(form),
            });

            if (response.status === 201) {
                const data = await response.json();
                success.value = true;
                return data.attendee as Attendee;
            }

            if (response.status === 409) {
                error.value = 'This email is already registered for this event.';
                return null;
            }

            if (response.status === 422) {
                const data = await response.json();
                const messages = Object.values(data.errors as Record<string, string[]>)
                    .flat()
                    .join(' ');
                error.value = messages;
                return null;
            }

            error.value = 'Something went wrong. Please try again.';
            return null;
        } catch {
            error.value = 'Network error. Please check your connection.';
            return null;
        } finally {
            loading.value = false;
        }
    }

    function reset(): void {
        loading.value = false;
        error.value = null;
        success.value = false;
    }

    return { loading, error, success, submitAttendee, reset };
}

function getCsrfToken(): string {
    const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
    return meta?.content ?? '';
}
