<?php

namespace Tests\Feature;

use App\CacheKeys;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class QueueStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_queue_status(): void
    {
        $response = $this->getJson('/queue/status');

        $response->assertOk();
        $response->assertJsonStructure([
            'player_count',
            'elo_distribution',
            'map_preferences',
            'format_preferences',
        ]);
        $response->assertJsonPath('player_count', 0);
    }

    public function test_returns_queue_player_count(): void
    {
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        Cache::put(CacheKeys::MATCHMAKING_QUEUE, [$p1->getKey(), $p2->getKey()]);

        $response = $this->getJson('/queue/status');

        $response->assertOk();
        $response->assertJsonPath('player_count', 2);
    }

    public function test_returns_elo_distribution(): void
    {
        $bronze = Player::factory()->create(['elo_rating' => 700]);
        $silver = Player::factory()->create(['elo_rating' => 900]);
        $gold = Player::factory()->create(['elo_rating' => 1200]);

        Cache::put(CacheKeys::MATCHMAKING_QUEUE, [
            $bronze->getKey(),
            $silver->getKey(),
            $gold->getKey(),
        ]);

        $response = $this->getJson('/queue/status');

        $response->assertOk();
        $dist = $response->json('elo_distribution');
        $this->assertEquals(1, $dist['bronze']);
        $this->assertEquals(1, $dist['silver']);
        $this->assertEquals(1, $dist['gold']);
    }

    public function test_returns_map_preferences(): void
    {
        Cache::put(CacheKeys::MATCHMAKING_QUEUE, ['p1', 'p2', 'p3']);
        Cache::put(CacheKeys::MATCHMAKING_QUEUE_MAPS, [
            'p1' => 'world-map',
            'p2' => 'world-map',
            'p3' => 'europe-map',
        ]);

        $response = $this->getJson('/queue/status');

        $response->assertOk();
        $maps = $response->json('map_preferences');
        $this->assertNotEmpty($maps);
    }

    public function test_returns_format_preferences(): void
    {
        Cache::put(CacheKeys::MATCHMAKING_QUEUE, ['p1', 'p2', 'p3']);
        Cache::put(CacheKeys::MATCHMAKING_QUEUE_FORMATS, [
            'p1' => 'classic',
            'p2' => 'bo3',
            'p3' => 'classic',
        ]);

        $response = $this->getJson('/queue/status');

        $response->assertOk();
        $formats = $response->json('format_preferences');
        $this->assertNotEmpty($formats);
    }
}
