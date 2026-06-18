import { reactive } from 'vue';

export interface EventFilters {
    status: string;
    dateFrom: string;
    dateTo: string;
    city: string;
}

export interface UseEventFiltersReturn {
    filters: EventFilters;
    applyFilters: (callback: (filters: EventFilters) => void) => void;
    reset: (callback?: (filters: EventFilters) => void) => void;
    toParams: () => URLSearchParams;
}

export function useEventFilters(initial?: Partial<EventFilters>): UseEventFiltersReturn {
    const filters = reactive<EventFilters>({
        status: initial?.status ?? '',
        dateFrom: initial?.dateFrom ?? '',
        dateTo: initial?.dateTo ?? '',
        city: initial?.city ?? '',
    });

    function applyFilters(callback: (filters: EventFilters) => void): void {
        callback(filters);
    }

    function reset(callback?: (filters: EventFilters) => void): void {
        filters.status = '';
        filters.dateFrom = '';
        filters.dateTo = '';
        filters.city = '';
        callback?.(filters);
    }

    function toParams(): URLSearchParams {
        const params = new URLSearchParams();
        if (filters.status) params.set('status', filters.status);
        if (filters.dateFrom) params.set('from', filters.dateFrom);
        if (filters.dateTo) params.set('to', filters.dateTo);
        if (filters.city) params.set('city', filters.city);
        return params;
    }

    return { filters, applyFilters, reset, toParams };
}
