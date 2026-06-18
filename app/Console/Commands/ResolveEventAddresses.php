<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ResolveEventAddressAction;
use App\DTOs\EventDTO;
use App\Repositories\EventRepository;
use Illuminate\Console\Command;

class ResolveEventAddresses extends Command
{
    protected $signature = 'events:resolve-addresses
                            {--limit=500 : Maximum number of events to process}
                            {--chunk=50  : Number of events to process per batch}';

    protected $description = 'Geocode events that do not yet have an address resolved from lat/lng';

    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly ResolveEventAddressAction $resolveEventAddressAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $chunk = (int) $this->option('chunk');

        $this->info("Resolving addresses for up to {$limit} events (chunk size: {$chunk})...");

        $processed = 0;
        $bar = $this->output->createProgressBar($limit);
        $bar->start();

        while ($processed < $limit) {
            $batchSize = min($chunk, $limit - $processed);
            $events    = $this->eventRepository->findWithoutAddress($batchSize);

            if ($events->isEmpty()) {
                break;
            }

            foreach ($events as $eventDTO) {
                /** @var EventDTO $eventDTO */
                $this->resolveEventAddressAction->execute($eventDTO->id);
                $processed++;
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. Resolved {$processed} event(s).");

        return self::SUCCESS;
    }
}
