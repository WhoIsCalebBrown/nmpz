<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Player;
use App\Models\Round;
use App\Models\SoloGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_stats_for_map(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        $game = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);

        Round::create([
            'game_id' => $game->getKey(),
            'round_number' => 1,
            'location_lat' => 0,
            'location_lng' => 0,
            'location_heading' => 0,
            'player_one_score' => 4000,
            'player_two_score' => 3000,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $response = $this->getJson("/maps/{$map->getKey()}/stats");

        $response->assertOk();
        $response->assertJsonStructure([
            'map' => ['id', 'name'],
            'total_games',
            'unique_players',
            'average_round_score',
            'best_round_score',
            'solo_games_played',
            'games_per_day',
        ]);

        $response->assertJsonPath('total_games', 1);
        $response->assertJsonPath('best_round_score', 4000);
        $response->assertJsonPath('map.id', $map->getKey());
    }

    public function test_returns_zero_stats_for_empty_map(): void
    {
        $map = $this->setupMap();

        $response = $this->getJson("/maps/{$map->getKey()}/stats");

        $response->assertOk();
        $response->assertJsonPath('total_games', 0);
        $response->assertJsonPath('unique_players', 0);
        $response->assertJsonPath('average_round_score', 0);
        $response->assertJsonPath('best_round_score', 0);
        $response->assertJsonPath('solo_games_played', 0);
    }

    public function test_counts_solo_games(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->create();

        SoloGame::factory()->completed()->create([
            'player_id' => $player->getKey(),
            'map_id' => $map->getKey(),
        ]);

        $response = $this->getJson("/maps/{$map->getKey()}/stats");

        $response->assertOk();
        $response->assertJsonPath('solo_games_played', 1);
    }

    public function test_excludes_other_map_data(): void
    {
        $map1 = $this->setupMap();
        $map2 = $this->setupMap(attributes: ['name' => 'other-map']);
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // Game on map2 only
        Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map2->getKey(),
        ]);

        $response = $this->getJson("/maps/{$map1->getKey()}/stats");

        $response->assertOk();
        $response->assertJsonPath('total_games', 0);
    }

    public function test_returns_games_per_day_trend(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
            'created_at' => now()->subDays(2),
        ]);

        Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
            'created_at' => now(),
        ]);

        $response = $this->getJson("/maps/{$map->getKey()}/stats");

        $response->assertOk();
        $gamesPerDay = $response->json('games_per_day');
        $this->assertCount(2, $gamesPerDay);
    }

    public function test_invalid_map_returns_404(): void
    {
        $response = $this->getJson('/maps/nonexistent-uuid/stats');

        $response->assertNotFound();
    }
}
