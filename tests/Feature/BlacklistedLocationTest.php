<?php

namespace Tests\Feature;

use App\Actions\CreateMatch;
use App\Models\Location;
use App\Models\Map;
use App\Models\Player;
use App\Services\DailyChallengeService;
use App\Services\SoloGameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BlacklistedLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_match_excludes_blacklisted_locations(): void
    {
        Event::fake();

        $map = Map::factory()->create(['name' => config('game.default_map')]);
        // MapFactory afterCreating creates 1 available location automatically
        Location::factory()->for($map)->blacklisted()->count(5)->create();

        $p1 = Player::factory()->withElo(1000)->create();
        $p2 = Player::factory()->withElo(1000)->create();

        $game = app(CreateMatch::class)->handle($p1, $p2);

        $round = $game->rounds()->first();
        $blacklistedIds = Location::where('map_id', $map->getKey())
            ->whereNotNull('blacklisted_at')
            ->pluck('id')
            ->toArray();

        $matchedLocation = Location::where('map_id', $map->getKey())
            ->where('lat', $round->location_lat)
            ->where('lng', $round->location_lng)
            ->first();

        $this->assertNotNull($matchedLocation);
        $this->assertNotContains($matchedLocation->getKey(), $blacklistedIds);
    }

    public function test_create_match_count_excludes_blacklisted(): void
    {
        $map = Map::factory()->create(['name' => config('game.default_map')]);
        // MapFactory afterCreating creates 1 available location
        // Blacklist it so there are zero available
        Location::where('map_id', $map->getKey())->update(['blacklisted_at' => now()]);

        $this->assertSame(0, Location::where('map_id', $map->getKey())->available()->count());
    }

    public function test_solo_game_excludes_blacklisted_locations(): void
    {
        $map = Map::factory()->create(['name' => config('game.default_map')]);
        // MapFactory creates 1 available location automatically
        $autoCreated = Location::where('map_id', $map->getKey())->first();
        Location::factory()->for($map)->blacklisted()->count(5)->create();
        $extraAvailable = Location::factory()->for($map)->count(2)->create();

        $availableIds = collect([$autoCreated])
            ->merge($extraAvailable)
            ->pluck('id')
            ->toArray();

        $player = Player::factory()->withElo(1000)->create();
        $service = app(SoloGameService::class);

        $game = $service->start($player, 'explorer', null, ['max_rounds' => 3]);

        foreach ($game->location_ids as $id) {
            $this->assertContains($id, $availableIds);
        }
    }

    public function test_daily_challenge_excludes_blacklisted_locations(): void
    {
        $map = Map::factory()->create(['name' => config('game.default_map')]);
        // MapFactory creates 1 available location automatically
        Location::factory()->for($map)->blacklisted()->count(5)->create();
        Location::factory()->for($map)->count(4)->create();

        $availableIds = Location::where('map_id', $map->getKey())
            ->available()
            ->pluck('id')
            ->toArray();

        $service = app(DailyChallengeService::class);
        $challenge = $service->getOrCreateForDate();

        foreach ($challenge->location_ids as $id) {
            $this->assertContains($id, $availableIds);
        }
    }

    public function test_available_scope_filters_blacklisted(): void
    {
        $map = Map::factory()->create();
        // MapFactory afterCreating adds 1 location automatically (available)
        Location::factory()->for($map)->count(2)->create();
        Location::factory()->for($map)->blacklisted()->count(2)->create();

        // 1 (auto) + 2 (manual) = 3 available, 2 blacklisted = 5 total
        $this->assertSame(5, Location::where('map_id', $map->getKey())->count());
        $this->assertSame(3, Location::where('map_id', $map->getKey())->available()->count());
    }
}
