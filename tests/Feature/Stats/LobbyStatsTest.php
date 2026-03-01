<?php

namespace Tests\Feature\Stats;

use App\CacheKeys;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LobbyStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_lobby_stats(): void
    {
        $response = $this->getJson('/lobby/stats');

        $response->assertOk();
        $response->assertJsonStructure([
            'queue_size',
            'active_games',
            'spectatable_games',
            'games_today',
            'avg_wait_seconds',
        ]);
    }

    public function test_counts_queue_size(): void
    {
        Cache::put(CacheKeys::MATCHMAKING_QUEUE, ['player1', 'player2', 'player3']);

        $response = $this->getJson('/lobby/stats');

        $response->assertOk();
        $response->assertJsonPath('queue_size', 3);
    }

    public function test_counts_active_games(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        Game::factory()->inProgress()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);

        $response = $this->getJson('/lobby/stats');

        $response->assertOk();
        $response->assertJsonPath('active_games', 1);
    }

    public function test_counts_spectatable_games(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        Game::factory()->inProgress()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
            'allow_spectators' => true,
        ]);

        Game::factory()->inProgress()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
            'allow_spectators' => false,
        ]);

        $response = $this->getJson('/lobby/stats');

        $response->assertOk();
        $response->assertJsonPath('active_games', 2);
        $response->assertJsonPath('spectatable_games', 1);
    }

    public function test_counts_games_completed_today(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // Game completed today
        Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);

        $response = $this->getJson('/lobby/stats');

        $response->assertOk();
        $response->assertJsonPath('games_today', 1);
    }

    public function test_returns_zero_defaults(): void
    {
        $response = $this->getJson('/lobby/stats');

        $response->assertOk();
        $response->assertJsonPath('queue_size', 0);
        $response->assertJsonPath('active_games', 0);
        $response->assertJsonPath('spectatable_games', 0);
        $response->assertJsonPath('games_today', 0);
        $response->assertJsonPath('avg_wait_seconds', 0);
    }

    public function test_calculates_average_wait_time(): void
    {
        Cache::put(CacheKeys::MATCHMAKING_QUEUE_TIMES, [10, 20, 30]);

        $response = $this->getJson('/lobby/stats');

        $response->assertOk();
        $response->assertJsonPath('avg_wait_seconds', 20);
    }
}
