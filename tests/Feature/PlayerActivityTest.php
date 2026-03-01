<?php

namespace Tests\Feature;

use App\CacheKeys;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PlayerActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_heartbeat_sets_online_status(): void
    {
        $player = Player::factory()->create();

        $response = $this->postJson("/players/{$player->getKey()}/heartbeat");

        $response->assertOk();
        $this->assertTrue(Cache::has(CacheKeys::playerOnline($player->getKey())));
    }

    public function test_status_returns_online_for_heartbeated_player(): void
    {
        $player = Player::factory()->create();
        Cache::put(CacheKeys::playerOnline($player->getKey()), true, 120);

        $response = $this->postJson('/players/activity', [
            'player_ids' => [$player->getKey()],
        ]);

        $response->assertOk();
        $response->assertJsonPath($player->getKey(), 'online');
    }

    public function test_status_returns_offline_for_no_heartbeat(): void
    {
        $player = Player::factory()->create();

        $response = $this->postJson('/players/activity', [
            'player_ids' => [$player->getKey()],
        ]);

        $response->assertOk();
        $response->assertJsonPath($player->getKey(), 'offline');
    }

    public function test_status_returns_in_game_for_active_game(): void
    {
        $this->setupMap();
        $game = Game::factory()->inProgress()->create();

        $response = $this->postJson('/players/activity', [
            'player_ids' => [$game->player_one_id],
        ]);

        $response->assertOk();
        $response->assertJsonPath($game->player_one_id, 'in_game');
    }

    public function test_in_game_takes_priority_over_online(): void
    {
        $this->setupMap();
        $game = Game::factory()->inProgress()->create();
        Cache::put(CacheKeys::playerOnline($game->player_one_id), true, 120);

        $response = $this->postJson('/players/activity', [
            'player_ids' => [$game->player_one_id],
        ]);

        $response->assertOk();
        $response->assertJsonPath($game->player_one_id, 'in_game');
    }

    public function test_batch_status_for_multiple_players(): void
    {
        $this->setupMap();
        $online = Player::factory()->create();
        $offline = Player::factory()->create();
        $game = Game::factory()->inProgress()->create();

        Cache::put(CacheKeys::playerOnline($online->getKey()), true, 120);

        $response = $this->postJson('/players/activity', [
            'player_ids' => [
                $online->getKey(),
                $offline->getKey(),
                $game->player_one_id,
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath($online->getKey(), 'online');
        $response->assertJsonPath($offline->getKey(), 'offline');
        $response->assertJsonPath($game->player_one_id, 'in_game');
    }

    public function test_requires_player_ids(): void
    {
        $response = $this->postJson('/players/activity', []);

        $response->assertStatus(422);
    }

    public function test_limits_to_50_player_ids(): void
    {
        $ids = array_map(fn () => fake()->uuid(), range(1, 51));

        $response = $this->postJson('/players/activity', [
            'player_ids' => $ids,
        ]);

        $response->assertStatus(422);
    }
}
