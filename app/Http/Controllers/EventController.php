<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\EventFilterDTO;
use App\Models\Event;
use App\Services\EventService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(
        private readonly EventService $eventService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Events/Index', [
            'filters' => [
                'status' => $request->status,
                'from' => $request->input('from', '2023-01-01'),
            ],
            'statuses' => ['draft', 'published', 'cancelled', 'sold_out'],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        [$events, $stats] = $this->loadListing($request);

        return response()->json([
            'data' => $events->items(),
            'current_page' => $events->currentPage(),
            'last_page' => $events->lastPage(),
            'total' => $events->total(),
            'stats' => $stats,
        ]);
    }

    public function show(Event $event): Response
    {
        $event->load('user');

        return Inertia::render('Events/Show', [
            'event' => $event,
        ]);
    }

    public function visualData(Request $request): JsonResponse
    {
        $filterDTO = new EventFilterDTO(
            status: $request->input('status'),
            dateFrom: $request->input('from'),
            dateTo: $request->input('to'),
            city: $request->input('city'),
            allowedStatuses: ['published', 'sold_out'],
        );

        $events = $this->eventService->getFilteredEvents($filterDTO);

        return response()->json([
            'data'         => $events->items(),
            'current_page' => $events->currentPage(),
            'last_page'    => $events->lastPage(),
            'total'        => $events->total(),
        ]);
    }

    public function visualOne(Request $request): Response
    {
        $filterDTO = new EventFilterDTO(
            status: $request->input('status'),
            dateFrom: $request->input('from'),
            dateTo: $request->input('to'),
            city: $request->input('city'),
            allowedStatuses: ['published', 'sold_out'],
        );

        $events = $this->eventService->getFilteredEvents($filterDTO);

        return Inertia::render('Events/VisualOne', [
            'events'   => $events,
            'filters'  => $filterDTO,
            'statuses' => ['published', 'sold_out'],
            'cities'   => $this->eventService->getCities(),
        ]);
    }

    public function visualTwo(Request $request): Response
    {
        $filterDTO = new EventFilterDTO(
            status: $request->input('status'),
            dateFrom: $request->input('from'),
            dateTo: $request->input('to'),
            city: $request->input('city'),
            allowedStatuses: ['published', 'sold_out'],
        );

        $events = $this->eventService->getFilteredEvents($filterDTO);

        return Inertia::render('Events/VisualTwo', [
            'events'   => $events,
            'filters'  => $filterDTO,
            'statuses' => ['published', 'sold_out'],
            'cities'   => $this->eventService->getCities(),
        ]);
    }

    /**
     * @return array{0: LengthAwarePaginator, 1: array{ms: int, bytes: int}}
     */
    private function loadListing(Request $request): array
    {
        $start = microtime(true);

        $events = Event::with('user')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_time')
            ->paginate(50)
            ->withQueryString();

        $stats = [
            'ms' => (int) round((microtime(true) - $start) * 1000),
            'bytes' => strlen((string) json_encode($events->items())),
        ];

        return [$events, $stats];
    }
}
