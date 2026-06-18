<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useIntersectionObserver } from '@vueuse/core';
import { toast } from 'vue-sonner';
import { MapPin, Calendar, Users, Tag, ChevronDown, ChevronUp, Filter, X } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
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

const filtersOpen = ref(false);

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

function applyFilters(): void {
    const params = toParams();
    window.location.search = params.toString();
}

function resetFilters(): void {
    citySearch.value = '';
    reset(() => applyFilters());
}

// ── Pagination / Infinite scroll ─────────────────────────────────────────────
const rows = ref<EventRow[]>([...props.events.data].sort((a, b) => (a.createdTime ?? 0) - (b.createdTime ?? 0)));
const currentPage = ref(props.events.current_page);
const lastPage = ref(props.events.last_page);
const total = ref(props.events.total);
const loading = ref(false);
const sentinel = ref<HTMLElement | null>(null);
let scrollObserver: IntersectionObserver | null = null;

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
        const sorted = [...page.data].sort((a, b) => (a.createdTime ?? 0) - (b.createdTime ?? 0));
        rows.value.push(...sorted);
        currentPage.value = page.current_page;
        lastPage.value = page.last_page;
        total.value = page.total;
    } catch {
        // silently ignore
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    scrollObserver = new IntersectionObserver(
        (entries) => { if (entries[0]?.isIntersecting) loadMore(); },
        { rootMargin: '400px' },
    );
    if (sentinel.value) scrollObserver.observe(sentinel.value);
});

onBeforeUnmount(() => scrollObserver?.disconnect());

// ── Group events by month/year ───────────────────────────────────────────────
interface MonthGroup {
    label: string;
    events: EventRow[];
}

const grouped = computed<MonthGroup[]>(() => {
    const map = new Map<string, EventRow[]>();
    for (const event of rows.value) {
        const label = event.createdTime
            ? new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' }).format(new Date(event.createdTime * 1000))
            : 'Unknown Date';
        if (!map.has(label)) map.set(label, []);
        map.get(label)!.push(event);
    }
    return Array.from(map.entries()).map(([label, events]) => ({ label, events }));
});

// ── Scroll-reveal animations ─────────────────────────────────────────────────
const itemRefs = ref<(HTMLElement | null)[]>([]);
const itemVisible = ref<boolean[]>([]);

function observeItem(el: HTMLElement | null, index: number): void {
    if (!el) return;
    const { stop } = useIntersectionObserver(
        ref(el),
        ([entry]) => {
            if (entry?.isIntersecting) {
                itemVisible.value[index] = true;
                stop();
            }
        },
        { threshold: 0.1 },
    );
}

// ── Register inline expand ────────────────────────────────────────────────────
const expandedEventId = ref<string | null>(null);
const form = reactive<AttendeeForm>({ name: '', email: '' });
const { loading: submitting, error: registerError, success: registerSuccess, submitAttendee, reset: resetAttendee } = useAttendee();

function toggleRegister(event: EventRow): void {
    if (expandedEventId.value === event.id) {
        expandedEventId.value = null;
    } else {
        expandedEventId.value = event.id;
        form.name = '';
        form.email = '';
        resetAttendee();
    }
}

async function handleRegister(event: EventRow): Promise<void> {
    const result = await submitAttendee(event.id, { ...form });
    if (result) {
        toast.success('You\'re registered!', {
            description: `Confirmation sent to ${result.email}`,
        });
        expandedEventId.value = null;
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
    <Head title="Events — Visual 2" />

    <div class="min-h-screen bg-background">
        <!-- Filter toggle bar -->
        <div class="sticky top-12 z-40 border-b border-border bg-background/95 backdrop-blur-sm">
            <div class="mx-auto max-w-4xl px-4 py-2">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-muted-foreground">{{ total.toLocaleString() }} events</span>
                    <Button variant="ghost" size="sm" @click="filtersOpen = !filtersOpen">
                        <Filter class="mr-1.5 size-4" />
                        Filters
                        <ChevronDown v-if="!filtersOpen" class="ml-1 size-4" />
                        <ChevronUp v-else class="ml-1 size-4" />
                    </Button>
                </div>

                <!-- Collapsible filter panel -->
                <div v-if="filtersOpen" class="border-t border-border pb-3 pt-3">
                    <form class="flex flex-wrap items-end gap-3" @submit.prevent="applyFilters">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs text-muted-foreground" for="v2-status">Status</label>
                            <select
                                id="v2-status"
                                v-model="filters.status"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">All</option>
                                <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div class="relative flex flex-col gap-1">
                            <label class="text-xs text-muted-foreground" for="v2-city">City</label>
                            <div class="relative">
                                <input
                                    id="v2-city"
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
                            <label class="text-xs text-muted-foreground" for="v2-from">From</label>
                            <input
                                id="v2-from"
                                v-model="filters.dateFrom"
                                type="date"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs text-muted-foreground" for="v2-to">To</label>
                            <input
                                id="v2-to"
                                v-model="filters.dateTo"
                                type="date"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            />
                        </div>
                        <Button type="submit" size="sm">Apply</Button>
                        <Button type="button" variant="ghost" size="sm" @click="resetFilters">Reset</Button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Timeline -->
        <div class="mx-auto max-w-4xl px-4 pb-16 pt-10">
            <h1 class="mb-10 text-3xl font-bold tracking-tight">Event Timeline</h1>

            <div v-if="rows.length === 0 && !loading" class="py-24 text-center text-muted-foreground">
                <Tag class="mx-auto mb-4 size-12 opacity-20" />
                <p class="text-lg font-medium">No events found</p>
            </div>

            <div v-for="group in grouped" :key="group.label" class="mb-12">
                <!-- Month header -->
                <div class="mb-6 flex items-center gap-4">
                    <div class="h-px flex-1 bg-border" />
                    <h2 class="shrink-0 text-sm font-semibold uppercase tracking-widest text-muted-foreground">
                        {{ group.label }}
                    </h2>
                    <div class="h-px flex-1 bg-border" />
                </div>

                <!-- Events in this month -->
                <div class="relative pl-8">
                    <!-- Vertical spine -->
                    <div class="absolute left-3 top-0 bottom-0 w-px bg-border" />

                    <div
                        v-for="(event, i) in group.events"
                        :key="event.id"
                        :ref="(el) => observeItem(el as HTMLElement | null, rows.indexOf(event))"
                        class="relative mb-8 transition-all duration-500"
                        :class="[
                            itemVisible[rows.indexOf(event)]
                                ? 'translate-x-0 opacity-100'
                                : 'translate-x-8 opacity-0',
                        ]"
                        :style="{ transitionDelay: `${i * 40}ms` }"
                    >
                        <!-- Dot on spine -->
                        <div class="absolute -left-5 top-4 size-3 rounded-full border-2 border-primary bg-background" />

                        <!-- Event card -->
                        <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                            <div class="flex gap-0">
                                <!-- Thumbnail -->
                                <div class="hidden shrink-0 overflow-hidden sm:block" style="width: 160px;">
                                    <img
                                        v-if="event.images[0]"
                                        :src="imageUrl(event.images[0].path)"
                                        :alt="event.payload.name"
                                        class="size-full object-cover"
                                        style="height: 100%;"
                                    />
                                    <div v-else class="flex size-full items-center justify-center bg-muted text-muted-foreground" style="height: 120px;">
                                        <Tag class="size-8 opacity-20" />
                                    </div>
                                </div>

                                <!-- Content -->
                                <div class="flex flex-1 flex-col gap-2 p-4">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                                {{ event.type }}
                                            </p>
                                            <h3 class="mt-0.5 text-base font-semibold leading-snug">
                                                {{ event.payload.name }}
                                            </h3>
                                        </div>
                                        <Badge :variant="statusVariant(event.status)" class="shrink-0 text-xs">
                                            {{ event.status }}
                                        </Badge>
                                    </div>

                                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                                        <span v-if="event.payload.venue?.name" class="flex items-center gap-1">
                                            <MapPin class="size-3.5" />{{ event.payload.venue.name }}
                                        </span>
                                        <span v-if="event.address" class="flex items-center gap-1 text-xs">
                                            <MapPin class="size-3 opacity-0" />{{ event.address }}
                                        </span>
                                        <span v-if="event.createdTime" class="flex items-center gap-1">
                                            <Calendar class="size-3.5" />
                                            <span class="text-xs">{{ formatDate(event.createdTime, event.timezone) }}</span>
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <Users class="size-3.5" />{{ event.attendeesCount }} registered
                                        </span>
                                    </div>

                                    <div v-if="event.payload.pricing?.min_price" class="text-sm font-medium">
                                        From ${{ event.payload.pricing.min_price }}
                                    </div>

                                    <!-- Inline register toggle -->
                                    <div class="mt-1">
                                        <button
                                            type="button"
                                            class="flex items-center gap-1 text-sm font-medium text-primary transition-opacity hover:opacity-80 disabled:opacity-40"
                                            :disabled="event.status === 'cancelled' || event.status === 'sold_out'"
                                            @click="toggleRegister(event)"
                                        >
                                            <span>{{ expandedEventId === event.id ? 'Cancel' : 'Register' }}</span>
                                            <ChevronDown
                                                class="size-4 transition-transform"
                                                :class="{ 'rotate-180': expandedEventId === event.id }"
                                            />
                                        </button>

                                        <!-- Inline form -->
                                        <div
                                            v-if="expandedEventId === event.id"
                                            class="mt-3 overflow-hidden rounded-lg border border-border bg-muted/30 p-4"
                                        >
                                            <form class="flex flex-col gap-3" @submit.prevent="handleRegister(event)">
                                                <div class="flex flex-col gap-1">
                                                    <label class="text-xs font-medium" :for="`name-${event.id}`">Name</label>
                                                    <input
                                                        :id="`name-${event.id}`"
                                                        v-model="form.name"
                                                        type="text"
                                                        required
                                                        placeholder="Jane Doe"
                                                        class="h-8 rounded-md border border-input bg-background px-3 text-sm placeholder:text-muted-foreground"
                                                    />
                                                </div>
                                                <div class="flex flex-col gap-1">
                                                    <label class="text-xs font-medium" :for="`email-${event.id}`">Email</label>
                                                    <input
                                                        :id="`email-${event.id}`"
                                                        v-model="form.email"
                                                        type="email"
                                                        required
                                                        placeholder="jane@example.com"
                                                        class="h-8 rounded-md border border-input bg-background px-3 text-sm placeholder:text-muted-foreground"
                                                    />
                                                </div>
                                                <p v-if="registerError" class="text-xs text-destructive">{{ registerError }}</p>
                                                <Button type="submit" size="sm" :disabled="submitting" class="self-start">
                                                    {{ submitting ? 'Registering…' : 'Confirm Registration' }}
                                                </Button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Infinite scroll sentinel -->
            <div ref="sentinel" class="flex justify-center py-4">
                <span v-if="loading" class="text-sm text-muted-foreground">Loading more…</span>
            </div>
        </div>
    </div>
</template>
