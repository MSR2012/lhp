<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedEventImages extends Command
{
    protected $signature = 'events:seed-images
                            {--chunk=2000 : Number of events to process per batch}';

    protected $description = 'Assign 2 placeholder images to every event that has none';

    private const IMAGE_PATHS = [
        'events/event-1.jpg', 'events/event-2.jpg', 'events/event-3.jpg', 'events/event-4.jpg',
        'events/event-5.jpg', 'events/event-6.jpg', 'events/event-7.jpg', 'events/event-8.jpg',
    ];

    private const IMAGES_PER_EVENT = 2;

    public function handle(): int
    {
        $chunk = (int) $this->option('chunk');

        $total = DB::table('events')
            ->whereNotIn('id', DB::table('event_images')->select('event_id'))
            ->count();

        if ($total === 0) {
            $this->info('All events already have images. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info("Seeding images for {$total} events (chunk: {$chunk})…");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $now = now()->toDateTimeString();
        $imageCount = count(self::IMAGE_PATHS);
        $processed  = 0;

        DB::table('events')
            ->whereNotIn('id', DB::table('event_images')->select('event_id'))
            ->select('id')
            ->orderBy('id')
            ->chunk($chunk, function ($events) use (&$processed, $bar, $now, $imageCount) {
                $imageBatch = [];

                foreach ($events as $event) {
                    $imageIndex = $processed % $imageCount;

                    for ($order = 0; $order < self::IMAGES_PER_EVENT; $order++) {
                        $imageBatch[] = [
                            'id'            => (string) Str::ulid(),
                            'event_id'      => $event->id,
                            'path'          => self::IMAGE_PATHS[($imageIndex + $order) % $imageCount],
                            'display_order' => $order,
                            'created_at'    => $now,
                            'updated_at'    => $now,
                        ];
                    }

                    $processed++;
                }

                DB::table('event_images')->insert($imageBatch);
                $bar->advance(count($events));
            });

        $bar->finish();
        $this->newLine();
        $this->info("Done. Added images for {$processed} event(s).");

        return self::SUCCESS;
    }
}
