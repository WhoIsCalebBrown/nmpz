<?php

namespace Tests\Feature\Stats;

use App\Models\Game;
use App\Models\Round;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_game_summary_with_stats(): void
    {
        $this->setupMap();
        $game = Game::factory()->completed()->create();

        $location = $game->map->locations()->first();
        Round::create([
            'location_heading' => 0,
            'game_id' => $game->getKey(),
            'round_number' => 1,
            'location_lat' => $location->lat,
            'location_lng' => $location->lng,
            'player_one_guess_lat' => $location->lat + 0.01,
            'player_one_guess_lng' => $location->lng + 0.01,
            'player_two_guess_lat' => $location->lat + 0.1,
            'player_two_guess_lng' => $location->lng + 0.1,
            'player_one_score' => 4500,
            'player_two_score' => 3000,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $response = $this->getJson("/games/{$game->getKey()}/summary");

        $response->assertOk();
        $response->assertJsonStructure([
            'game_id',
            'winner_id',
            'match_format',
            'map_name',
            'total_rounds',
            'player_one' => [
                'id', 'name', 'elo_rating', 'rank', 'rating_change',
                'total_score', 'average_score', 'best_round_score', 'worst_round_score',
                'rounds_won', 'average_distance_km', 'closest_guess_km', 'total_health_remaining',
            ],
            'player_two' => [
                'id', 'name', 'elo_rating', 'rank', 'rating_change',
                'total_score', 'average_score', 'best_round_score', 'worst_round_score',
                'rounds_won', 'average_distance_km', 'closest_guess_km', 'total_health_remaining',
            ],
        ]);

        $response->assertJsonPath('total_rounds', 1);
        $response->assertJsonPath('player_one.total_score', 4500);
        $response->assertJsonPath('player_one.best_round_score', 4500);
        $response->assertJsonPath('player_one.rounds_won', 1);
        $response->assertJsonPath('player_two.total_score', 3000);
        $response->assertJsonPath('player_two.rounds_won', 0);
    }

    public function test_summary_with_multiple_rounds(): void
    {
        $this->setupMap();
        $game = Game::factory()->completed()->create();
        $location = $game->map->locations()->first();

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

        Round::create([
            'location_heading' => 0,
            'game_id' => $game->getKey(),
            'round_number' => 2,
            'location_lat' => $location->lat,
            'location_lng' => $location->lng,
            'player_one_score' => 2000,
            'player_two_score' => 4000,
            'player_one_guess_lat' => $location->lat + 2,
            'player_one_guess_lng' => $location->lng + 2,
            'player_two_guess_lat' => $location->lat + 0.5,
            'player_two_guess_lng' => $location->lng + 0.5,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $response = $this->getJson("/games/{$game->getKey()}/summary");

        $response->assertOk();
        $response->assertJsonPath('total_rounds', 2);
        $response->assertJsonPath('player_one.total_score', 7000);
        $response->assertJsonPath('player_one.best_round_score', 5000);
        $response->assertJsonPath('player_one.worst_round_score', 2000);
        $response->assertJsonPath('player_one.rounds_won', 1);
        $response->assertJsonPath('player_two.total_score', 7000);
        $response->assertJsonPath('player_two.rounds_won', 1);
    }

    public function test_summary_handles_no_guesses(): void
    {
        $this->setupMap();
        $game = Game::factory()->completed()->create();
        $location = $game->map->locations()->first();

        Round::create([
            'location_heading' => 0,
            'game_id' => $game->getKey(),
            'round_number' => 1,
            'location_lat' => $location->lat,
            'location_lng' => $location->lng,
            'player_one_score' => 0,
            'player_two_score' => 0,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $response = $this->getJson("/games/{$game->getKey()}/summary");

        $response->assertOk();
        $response->assertJsonPath('player_one.average_distance_km', null);
        $response->assertJsonPath('player_two.closest_guess_km', null);
    }

    public function test_summary_returns_404_for_invalid_game(): void
    {
        $response = $this->getJson('/games/nonexistent-uuid/summary');

        $response->assertNotFound();
    }
}
