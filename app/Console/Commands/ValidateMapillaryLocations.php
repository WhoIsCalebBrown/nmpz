<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Map;
use App\Services\MapillaryService;
use Illuminate\Console\Command;

class ValidateMapillaryLocations extends Command
{
    protected $signature = 'mapillary:validate
        {--map= : Filter to a specific map name}
        {--sleep=200 : Milliseconds to sleep between API calls}
        {--recheck : Re-validate already-blacklisted locations}
        {--dry-run : Report without modifying the database}';

    protected $description = 'Validate locations against the Mapillary API and blacklist those without coverage';

    public function handle(MapillaryService $mapillary): int
    {
        $token = config('services.mapillary.access_token');
        if (! $token) {
            $this->error('Mapillary access token is not configured. Set MAPILLARY_ACCESS_TOKEN or VITE_MAPILLARY_ACCESS_TOKEN.');

            return self::FAILURE;
        }

        $query = Location::query();

        if ($mapName = $this->option('map')) {
            $map = Map::where('name', $mapName)->first();
            if (! $map) {
                $this->error("Map '{$mapName}' not found.");

                return self::FAILURE;
            }
            $query->where('map_id', $map->getKey());
        }

        if (! $this->option('recheck')) {
            $query->available();
        }

        $locations = $query->get();
        $total = $locations->count();

        if ($total === 0) {
            $this->info('No locations to validate.');

            return self::SUCCESS;
        }

        $this->info("Validating {$total} locations...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $sleepMs = (int) $this->option('sleep');
        $dryRun = $this->option('dry-run');
        $blacklisted = 0;
        $restored = 0;

        foreach ($locations as $location) {
            $hasImage = $mapillary->hasImageAt($location->lat, $location->lng);

            if (! $hasImage && ! $location->blacklisted_at) {
                $blacklisted++;
                if (! $dryRun) {
                    $location->update(['blacklisted_at' => now()]);
                }
                $this->output->write(' <fg=red>X</>');
            } elseif ($hasImage && $location->blacklisted_at) {
                $restored++;
                if (! $dryRun) {
                    $location->update(['blacklisted_at' => null]);
                }
                $this->output->write(' <fg=green>R</>');
            }

            $bar->advance();

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $bar->finish();
        $this->newLine(2);

        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $this->info("{$prefix}Done. Blacklisted: {$blacklisted}, Restored: {$restored}");

        return self::SUCCESS;
    }
}
