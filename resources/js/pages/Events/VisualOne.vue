<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { toast } from 'vue-sonner';
import { MapPin, Calendar, Users, Tag, X } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import type { EventRow } from '@/types/event';
import type { AttendeeForm } from '@/types/attendee';
import { useAttendee } from '@/composables/useAttendee';
import { useEventFilters } from '@/composables/useEventFilters';

interface Paginator {
    data: EventRow[];
    current_page: number;
    last_page: number;
    total: number;
}

interface EventFiltersProps {
    status: string | null;
    dateFrom: string | null;
    dateTo: string | null;
    city: string | null;
}

const props = defineProps<{
    events: Paginator;
    filters: EventFiltersProps;
    statuses: string[];
    cities: string[];
}>();

// ── Filters ─────────────────────────────────────────────────────────────────
const { filters, toParams, reset } = useEventFilters({
    status: props.filters.status ?? '',
    dateFrom: props.filters.dateFrom ?? '',
    dateTo: props.filters.dateTo ?? '',
    city: props.filters.city ?? '',
});

// ── City search ──────────────────────────────────────────────────────────────
const citySearch = ref(props.filters.city ?? '');
const cityDropdownOpen = ref(false);
const filteredCities = computed(() =>
    citySearch.value.trim() === ''
        ? props.cities
        : props.cities.filter((c) => c.toLowerCase().includes(citySearch.value.toLowerCase())),
);

function selectCity(city: string): void {
    citySearch.value = city;
    filters.city = city;
    cityDropdownOpen.value = false;
}

function clearCity(): void {
    citySearch.value = '';
    filters.city = '';
}

watch(citySearch, (val) => {
    if (val === '') filters.city = '';
});

function scheduleCityDropdownClose(): void {
    setTimeout(() => { cityDropdownOpen.value = false; }, 150);
}

// ── Pagination / Infinite scroll ─────────────────────────────────────────────
const rows = ref<EventRow[]>([...props.events.data]);
const currentPage = ref(props.events.current_page);
const lastPage = ref(props.events.last_page);
const total = ref(props.events.total);
const loading = ref(false);
const sentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

const hasMore = computed(() => currentPage.value < lastPage.value);

async function loadMore(): Promise<void> {
    if (loading.value || !hasMore.value) return;
    loading.value = true;

    const params = toParams();
    params.set('page', String(currentPage.value + 1));

    try {
        const response = await fetch(`/events-visual/data?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        const page = await response.json() as Paginator;
        rows.value.push(...page.data);
        currentPage.value = page.current_page;
        lastPage.value = page.last_page;
        total.value = page.total;
    } catch {
        // silently ignore fetch errors on scroll
    } finally {
        loading.value = false;
    }
}

function applyFilters(): void {
    rows.value = [];
    currentPage.value = 0;
    lastPage.value = 1;

    const params = toParams();
    window.location.search = params.toString();
}

function resetFilters(): void {
    citySearch.value = '';
    reset(() => applyFilters());
}

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0]?.isIntersecting) loadMore();
        },
        { rootMargin: '400px' },
    );
    if (sentinel.value) observer.observe(sentinel.value);
});

onBeforeUnmount(() => observer?.disconnect());

// ── Register modal ─────────────────────────────────────────────────────────
const selectedEvent = ref<EventRow | null>(null);
const dialogOpen = ref(false);
const form = reactive<AttendeeForm>({ name: '', email: '' });
const { loading: submitting, error: registerError, success: registerSuccess, submitAttendee, reset: resetAttendee } = useAttendee();

function openRegister(event: EventRow): void {
    selectedEvent.value = event;
    form.name = '';
    form.email = '';
    resetAttendee();
    dialogOpen.value = true;
}

async function handleRegister(): Promise<void> {
    if (!selectedEvent.value) return;
    const result = await submitAttendee(selectedEvent.value.id, { ...form });
    if (result) {
        toast.success('You\'re registered!', {
            description: `Confirmation sent to ${result.email}`,
        });
        dialogOpen.value = false;
    }
}

// ── Helpers ─────────────────────────────────────────────────────────────────
function formatDate(timestamp: number | null, timezone: string | null): string {
    if (!timestamp) return '—';
    return new Intl.DateTimeFormat('en-US', {
        timeZone: timezone ?? 'UTC',
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZoneName: 'short',
    }).format(new Date(timestamp * 1000));
}

function imageUrl(path: string): string {
    return `/images/${path}`;
}

const statusVariant = (status: string): 'default' | 'destructive' | 'secondary' | 'outline' => {
    switch (status) {
        case 'published': return 'default';
        case 'cancelled': return 'destructive';
        case 'sold_out':  return 'secondary';
        default:          return 'outline';
    }
};
</script>

<template>
    <Head title="Events — Visual 1" />

    <div class="min-h-screen bg-background">
        <!-- Filter bar -->
        <div class="sticky top-12 z-40 border-b border-border bg-background/95 backdrop-blur-sm">
            <div class="mx-auto max-w-7xl px-4 py-3">
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="applyFilters">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-muted-foreground" for="v1-status">Status</label>
                        <select
                            id="v1-status"
                            v-model="filters.status"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">All</option>
                            <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>
                    <div class="relative flex flex-col gap-1">
                        <label class="text-xs text-muted-foreground" for="v1-city">City</label>
                        <div class="relative">
                            <input
                                id="v1-city"
                                v-model="citySearch"
                                type="text"
                                placeholder="Search city…"
                                autocomplete="off"
                                class="h-9 w-48 rounded-md border border-input bg-background px-3 pr-7 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                                @focus="cityDropdownOpen = true"
                                @blur="scheduleCityDropdownClose()"
                            />
                            <button
                                v-if="citySearch"
                                type="button"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                @click="clearCity"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>
                        <ul
                            v-if="cityDropdownOpen && filteredCities.length"
                            class="absolute top-full z-50 mt-1 max-h-56 w-48 overflow-auto rounded-md border border-border bg-popover shadow-md"
                        >
                            <li
                                v-for="city in filteredCities"
                                :key="city"
                                class="cursor-pointer px-3 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground"
                                @mousedown.prevent="selectCity(city)"
                            >
                                {{ city }}
                            </li>
                        </ul>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-muted-foreground" for="v1-from">From</label>
                        <input
                            id="v1-from"
                            v-model="filters.dateFrom"
                            type="date"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs text-muted-foreground" for="v1-to">To</label>
                        <input
                            id="v1-to"
                            v-model="filters.dateTo"
                            type="date"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        />
                    </div>
                    <Button type="submit" size="sm">Filter</Button>
                    <Button type="button" variant="ghost" size="sm" @click="resetFilters">Reset</Button>
                </form>
            </div>
        </div>

        <!-- Header -->
        <div class="mx-auto max-w-7xl px-4 pt-8 pb-4">
            <h1 class="text-3xl font-bold tracking-tight">Events</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ total.toLocaleString() }} event{{ total === 1 ? '' : 's' }} found
            </p>
        </div>

        <!-- Card grid -->
        <div class="mx-auto max-w-7xl px-4 pb-12">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="(event, index) in rows"
                    :key="event.id"
                    class="group flex flex-col overflow-hidden rounded-xl border border-border bg-card shadow-sm transition-all duration-200 hover:-translate-y-1 hover:shadow-md animate-fade-up"
                    :style="{ animationDelay: `${Math.min(index, 11) * 50}ms`, animationFillMode: 'both' }"
                >
                    <!-- Hero image -->
                    <div class="relative aspect-[16/9] overflow-hidden bg-muted">
                        <img
                            v-if="event.images[0]"
                            :src="imageUrl(event.images[0].path)"
                            :alt="event.payload.name"
                            class="size-full object-cover transition-opacity duration-300 group-hover:opacity-0"
                        />
                        <img
                            v-if="event.images[1]"
                            :src="imageUrl(event.images[1].path)"
                            :alt="event.payload.name"
                            class="absolute inset-0 size-full object-cover opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                        />
                        <div v-if="!event.images[0]" class="flex size-full items-center justify-center text-muted-foreground">
                            <Tag class="size-12 opacity-20" />
                        </div>
                        <div class="absolute left-3 top-3">
                            <Badge :variant="statusVariant(event.status)" class="text-xs">
                                {{ event.status }}
                            </Badge>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="flex flex-1 flex-col gap-3 p-4">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                {{ event.type }}
                            </p>
                            <h2 class="mt-0.5 line-clamp-2 text-base font-semibold leading-snug">
                                {{ event.payload.name }}
                            </h2>
                        </div>

                        <div class="flex flex-col gap-1.5 text-sm text-muted-foreground">
                            <div v-if="event.payload.venue?.name" class="flex items-center gap-1.5">
                                <MapPin class="size-3.5 shrink-0" />
                                <span class="truncate">{{ event.payload.venue.name }}</span>
                            </div>
                            <div v-if="event.address" class="flex items-center gap-1.5">
                                <MapPin class="size-3.5 shrink-0 opacity-0" />
                                <span class="truncate text-xs">{{ event.address }}</span>
                            </div>
                            <div v-if="event.createdTime" class="flex items-center gap-1.5">
                                <Calendar class="size-3.5 shrink-0" />
                                <span class="text-xs">{{ formatDate(event.createdTime, event.timezone) }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <Users class="size-3.5 shrink-0" />
                                <span class="text-xs">{{ event.attendeesCount }} registered</span>
                            </div>
                        </div>

                        <div class="mt-auto">
                            <Button
                                class="w-full"
                                size="sm"
                                :disabled="event.status === 'cancelled' || event.status === 'sold_out'"
                                @click="openRegister(event)"
                            >
                                Register
                            </Button>
                        </div>
                    </div>
                </article>
            </div>

            <!-- Empty state -->
            <div v-if="rows.length === 0 && !loading" class="py-24 text-center text-muted-foreground">
                <Tag class="mx-auto mb-4 size-12 opacity-20" />
                <p class="text-lg font-medium">No events found</p>
                <p class="text-sm">Try adjusting your filters</p>
            </div>

            <!-- Loading / sentinel -->
            <div ref="sentinel" class="mt-8 flex justify-center">
                <span v-if="loading" class="text-sm text-muted-foreground">Loading more events…</span>
            </div>
        </div>
    </div>

    <!-- Register modal -->
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Register for this event</DialogTitle>
                <DialogDescription v-if="selectedEvent">
                    {{ selectedEvent.payload.name }}
                </DialogDescription>
            </DialogHeader>

            <form class="flex flex-col gap-4 pt-2" @submit.prevent="handleRegister">
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium" for="reg-name">Name</label>
                    <input
                        id="reg-name"
                        v-model="form.name"
                        type="text"
                        required
                        placeholder="Jane Doe"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium" for="reg-email">Email</label>
                    <input
                        id="reg-email"
                        v-model="form.email"
                        type="email"
                        required
                        placeholder="jane@example.com"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>

                <p v-if="registerError" class="text-sm text-destructive">{{ registerError }}</p>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="ghost" @click="dialogOpen = false">
                        <X class="mr-1 size-4" />
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="submitting">
                        {{ submitting ? 'Registering…' : 'Confirm' }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
