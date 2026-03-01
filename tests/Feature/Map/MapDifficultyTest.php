<?php

namespace Tests\Feature\Map;

use App\Models\Game;
use App\Models\Round;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapDifficultyTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_when_no_games_played(): void
    {
        $this->setupMap();

        $response = $this->getJson('/maps/difficulty');

        $response->assertOk();
        $response->assertJsonPath('maps', []);
    }

    public function test_calculates_difficulty_for_map_with_games(): void
    {
        $map = $this->setupMap(10, ['is_active' => true]);
        $game = Game::factory()->completed()->create(['map_id' => $map->getKey()]);
        $location = $map->locations()->first();

        Round::create([
            'location_heading' => 0,
            'game_id' => $game->getKey(),
            'round_number' => 1,
            'location_lat' => $location->lat,
            'location_lng' => $location->lng,
            'player_one_score' => 4500,
            'player_two_score' => 4200,
            'player_one_guess_lat' => $location->lat,
            'player_one_guess_lng' => $location->lng,
            'player_two_guess_lat' => $location->lat + 0.1,
            'player_two_guess_lng' => $location->lng + 0.1,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $response = $this->getJson('/maps/difficulty');

        $response->assertOk();
        $response->assertJsonCount(1, 'maps');
        $response->assertJsonPath('maps.0.difficulty', 'easy');
        $response->assertJsonPath('maps.0.total_games', 1);
        $response->assertJsonPath('maps.0.total_rounds', 1);
    }

    public function test_hard_difficulty_for_low_scores(): void
    {
        $map = $this->setupMap(10, ['is_active' => true]);
        $game = Game::factory()->completed()->create(['map_id' => $map->getKey()]);
        $location = $map->locations()->first();

        Round::create([
            'location_heading' => 0,
            'game_id' => $game->getKey(),
            'round_number' => 1,
            'location_lat' => $location->lat,
            'location_lng' => $location->lng,
            'player_one_score' => 2500,
            'player_two_score' => 2200,
            'player_one_guess_lat' => $location->lat + 1,
            'player_one_guess_lng' => $location->lng + 1,
            'player_two_guess_lat' => $location->lat + 2,
            'player_two_guess_lng' => $location->lng + 2,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $response = $this->getJson('/maps/difficulty');

        $response->assertOk();
        $response->assertJsonPath('maps.0.difficulty', 'hard');
    }

    public function test_calculates_perfect_round_rate(): void
    {
        $map = $this->setupMap(10, ['is_active' => true]);
        $game = Game::factory()->completed()->create(['map_id' => $map->getKey()]);
        $location = $map->locations()->first();

        // Perfect round
        Round::create([
            'location_heading' => 0,
            'game_id' => $game->getKey(),
            'round_number' => 1,
            'location_lat' => $location->lat,
            'location_lng' => $location->lng,
            'player_one_score' => 5000,
            'player_two_score' => 3000,
            'player_one_guess_lat' => $location->lat,
            'player_one_guess_lng' => $location->lng,
            'player_two_guess_lat' => $location->lat + 1,
            'player_two_guess_lng' => $location->lng + 1,
            'started_at' => now()->subMinutes(4),
            'finished_at' => now()->subMinutes(3),
        ]);

        // Non-perfect round
        Round::create([
            'location_heading' => 0,
            'game_id' => $game->getKey(),
            'round_number' => 2,
            'location_lat' => $location->lat,
            'location_lng' => $location->lng,
            'player_one_score' => 3000,
            'player_two_score' => 3000,
            'player_one_guess_lat' => $location->lat + 1,
            'player_one_guess_lng' => $location->lng + 1,
            'player_two_guess_lat' => $location->lat + 1,
            'player_two_guess_lng' => $location->lng + 1,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $response = $this->getJson('/maps/difficulty');

        $response->assertOk();
        $this->assertEquals(50.0, $response->json('maps.0.perfect_round_rate'));
    }

    public function test_sorts_maps_by_average_score_ascending(): void
    {
        $easyMap = $this->setupMap(10, ['is_active' => true, 'name' => 'easy-map', 'display_name' => 'Easy Map']);
        $hardMap = $this->setupMap(10, ['is_active' => true, 'name' => 'hard-map', 'display_name' => 'Hard Map']);

        $easyGame = Game::factory()->completed()->create(['map_id' => $easyMap->getKey()]);
        $hardGame = Game::factory()->completed()->create(['map_id' => $hardMap->getKey()]);

        $easyLocation = $easyMap->locations()->first();
        $hardLocation = $hardMap->locations()->first();

        Round::create([
            'location_heading' => 0,
            'game_id' => $easyGame->getKey(),
            'round_number' => 1,
            'location_lat' => $easyLocation->lat,
            'location_lng' => $easyLocation->lng,
            'player_one_score' => 4500,
            'player_two_score' => 4500,
            'player_one_guess_lat' => $easyLocation->lat,
            'player_one_guess_lng' => $easyLocation->lng,
            'player_two_guess_lat' => $easyLocation->lat,
            'player_two_guess_lng' => $easyLocation->lng,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        Round::create([
            'location_heading' => 0,
            'game_id' => $hardGame->getKey(),
            'round_number' => 1,
            'location_lat' => $hardLocation->lat,
            'location_lng' => $hardLocation->lng,
            'player_one_score' => 1500,
            'player_two_score' => 1500,
            'player_one_guess_lat' => $hardLocation->lat + 5,
            'player_one_guess_lng' => $hardLocation->lng + 5,
            'player_two_guess_lat' => $hardLocation->lat + 5,
            'player_two_guess_lng' => $hardLocation->lng + 5,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $response = $this->getJson('/maps/difficulty');

        $response->assertOk();
        // Hard map first (lower average score)
        $response->assertJsonPath('maps.0.name', 'Hard Map');
        $response->assertJsonPath('maps.0.difficulty', 'extreme');
        $response->assertJsonPath('maps.1.name', 'Easy Map');
        $response->assertJsonPath('maps.1.difficulty', 'easy');
    }

    public function test_excludes_inactive_maps(): void
    {
        $map = $this->setupMap(10, ['is_active' => false]);
        $game = Game::factory()->completed()->create(['map_id' => $map->getKey()]);
        $location = $map->locations()->first();

        Round::create([
            'location_heading' => 0,
            'game_id' => $game->getKey(),
            'round_number' => 1,
            'location_lat' => $location->lat,
            'location_lng' => $location->lng,
            'player_one_score' => 3000,
            'player_two_score' => 3000,
            'player_one_guess_lat' => $location->lat,
            'player_one_guess_lng' => $location->lng,
            'player_two_guess_lat' => $location->lat,
            'player_two_guess_lng' => $location->lng,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $response = $this->getJson('/maps/difficulty');

        $response->assertOk();
        $response->assertJsonPath('maps', []);
    }
}
