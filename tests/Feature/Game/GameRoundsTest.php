<?php

namespace Tests\Feature\Game;

use App\Models\Game;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameRoundsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_rounds_for_game(): void
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
            'location_lat' => 48.8566,
            'location_lng' => 2.3522,
            'location_heading' => 0,
            'player_one_guess_lat' => 48.8,
            'player_one_guess_lng' => 2.3,
            'player_one_score' => 4500,
            'player_one_locked_in' => true,
            'player_two_guess_lat' => 40.0,
            'player_two_guess_lng' => -3.0,
            'player_two_score' => 2000,
            'player_two_locked_in' => true,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $response = $this->getJson("/games/{$game->getKey()}/rounds");

        $response->assertOk();
        $response->assertJsonPath('game_id', $game->getKey());
        $response->assertJsonStructure([
            'game_id',
            'player_one' => ['id', 'name'],
            'player_two' => ['id', 'name'],
            'rounds' => [[
                'round_number',
                'location' => ['lat', 'lng'],
                'player_one' => ['score', 'guess', 'distance_km', 'locked_in'],
                'player_two' => ['score', 'guess', 'distance_km', 'locked_in'],
                'started_at',
                'finished_at',
            ]],
        ]);

        $round = $response->json('rounds.0');
        $this->assertEquals(1, $round['round_number']);
        $this->assertEquals(4500, $round['player_one']['score']);
        $this->assertEquals(2000, $round['player_two']['score']);
        $this->assertNotNull($round['player_one']['distance_km']);
        $this->assertNotNull($round['player_two']['distance_km']);
    }

    public function test_excludes_unfinished_rounds(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        $game = Game::factory()->inProgress()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);

        // Finished round
        Round::create([
            'game_id' => $game->getKey(),
            'round_number' => 1,
            'location_lat' => 0,
            'location_lng' => 0,
            'location_heading' => 0,
            'player_one_score' => 3000,
            'player_two_score' => 2000,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(3),
        ]);

        // Unfinished round
        Round::create([
            'game_id' => $game->getKey(),
            'round_number' => 2,
            'location_lat' => 10,
            'location_lng' => 10,
            'location_heading' => 0,
            'started_at' => now()->subMinute(),
        ]);

        $response = $this->getJson("/games/{$game->getKey()}/rounds");

        $response->assertOk();
        $rounds = $response->json('rounds');
        $this->assertCount(1, $rounds);
        $this->assertEquals(1, $rounds[0]['round_number']);
    }

    public function test_handles_null_guesses(): void
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
            'player_one_score' => 0,
            'player_two_score' => 3000,
            'player_two_guess_lat' => 1.0,
            'player_two_guess_lng' => 1.0,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $response = $this->getJson("/games/{$game->getKey()}/rounds");

        $response->assertOk();
        $round = $response->json('rounds.0');
        $this->assertNull($round['player_one']['guess']);
        $this->assertNull($round['player_one']['distance_km']);
        $this->assertNotNull($round['player_two']['guess']);
    }

    public function test_rounds_ordered_by_round_number(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        $game = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);

        // Create rounds out of order
        Round::create([
            'game_id' => $game->getKey(),
            'round_number' => 3,
            'location_lat' => 0, 'location_lng' => 0, 'location_heading' => 0,
            'player_one_score' => 1000, 'player_two_score' => 1000,
            'started_at' => now()->subMinutes(2), 'finished_at' => now()->subMinute(),
        ]);

        Round::create([
            'game_id' => $game->getKey(),
            'round_number' => 1,
            'location_lat' => 0, 'location_lng' => 0, 'location_heading' => 0,
            'player_one_score' => 3000, 'player_two_score' => 3000,
            'started_at' => now()->subMinutes(6), 'finished_at' => now()->subMinutes(5),
        ]);

        Round::create([
            'game_id' => $game->getKey(),
            'round_number' => 2,
            'location_lat' => 0, 'location_lng' => 0, 'location_heading' => 0,
            'player_one_score' => 2000, 'player_two_score' => 2000,
            'started_at' => now()->subMinutes(4), 'finished_at' => now()->subMinutes(3),
        ]);

        $response = $this->getJson("/games/{$game->getKey()}/rounds");

        $response->assertOk();
        $rounds = $response->json('rounds');
        $this->assertEquals(1, $rounds[0]['round_number']);
        $this->assertEquals(2, $rounds[1]['round_number']);
        $this->assertEquals(3, $rounds[2]['round_number']);
    }

    public function test_unknown_game_returns_404(): void
    {
        $response = $this->getJson('/games/nonexistent-uuid/rounds');

        $response->assertNotFound();
    }
}
