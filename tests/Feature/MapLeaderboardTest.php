<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Map;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_leaderboard_for_map(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // 2 completed games for same players on this map
        for ($i = 0; $i < 2; $i++) {
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
        }

        $response = $this->getJson("/maps/{$map->getKey()}/leaderboard");

        $response->assertOk();
        $response->assertJsonPath('map.id', $map->getKey());
        $response->assertJsonStructure([
            'map' => ['id', 'name'],
            'entries' => [['player_id', 'player_name', 'games_played', 'total_score', 'average_score', 'best_round_score']],
        ]);

        $entries = $response->json('entries');
        $this->assertCount(2, $entries); // Both players qualify with 2 games
    }

    public function test_requires_minimum_two_games(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // Only 1 game
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
            'player_one_score' => 5000,
            'player_two_score' => 3000,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $response = $this->getJson("/maps/{$map->getKey()}/leaderboard");

        $response->assertOk();
        $this->assertEmpty($response->json('entries'));
    }

    public function test_sorts_by_total_score(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        for ($i = 0; $i < 2; $i++) {
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
                'player_one_score' => 5000,
                'player_two_score' => 2000,
                'started_at' => now()->subMinutes(2),
                'finished_at' => now()->subMinute(),
            ]);
        }

        $response = $this->getJson("/maps/{$map->getKey()}/leaderboard");

        $entries = $response->json('entries');
        // p1 should be first (higher total score)
        $this->assertEquals($p1->getKey(), $entries[0]['player_id']);
        $this->assertEquals(10000, $entries[0]['total_score']);
    }

    public function test_excludes_other_map_games(): void
    {
        $map1 = $this->setupMap();
        $map2 = Map::factory()->create(['name' => 'other-map']);

        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // Games on map2 only
        for ($i = 0; $i < 2; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map2->getKey(),
            ]);
            Round::create([
                'game_id' => $game->getKey(),
                'round_number' => 1,
                'location_lat' => 0,
                'location_lng' => 0,
                'location_heading' => 0,
                'player_one_score' => 5000,
                'player_two_score' => 3000,
                'started_at' => now()->subMinutes(2),
                'finished_at' => now()->subMinute(),
            ]);
        }

        $response = $this->getJson("/maps/{$map1->getKey()}/leaderboard");

        $response->assertOk();
        $this->assertEmpty($response->json('entries'));
    }

    public function test_invalid_map_returns_404(): void
    {
        $response = $this->getJson('/maps/nonexistent-uuid/leaderboard');

        $response->assertNotFound();
    }
}
