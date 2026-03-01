<?php

namespace Tests\Feature\Game;

use App\Models\Game;
use App\Models\Round;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_round_by_round_breakdown(): void
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

        $response = $this->getJson("/games/{$game->getKey()}/report");

        $response->assertOk();
        $response->assertJsonStructure([
            'game_id',
            'winner_id',
            'match_format',
            'map_name',
            'total_rounds',
            'player_one' => ['id', 'name'],
            'player_two' => ['id', 'name'],
            'rounds' => [['round_number', 'winner', 'player_one_score', 'player_two_score', 'margin', 'player_one_distance_km', 'player_two_distance_km']],
            'momentum' => [['round_number', 'leader', 'player_one_cumulative', 'player_two_cumulative', 'difference']],
            'lead_changes',
            'comebacks',
            'closest_round',
            'biggest_blowout',
        ]);

        $response->assertJsonPath('total_rounds', 1);
        $response->assertJsonPath('rounds.0.winner', 'player_one');
        $response->assertJsonPath('rounds.0.margin', 1500);
    }

    public function test_tracks_momentum_and_lead_changes(): void
    {
        $this->setupMap();
        $game = Game::factory()->completed()->create();
        $location = $game->map->locations()->first();

        // Round 1: p1 leads
        Round::create([
            'location_heading' => 0,
            'game_id' => $game->getKey(),
            'round_number' => 1,
            'location_lat' => $location->lat,
            'location_lng' => $location->lng,
            'player_one_score' => 4000,
            'player_two_score' => 2000,
            'player_one_guess_lat' => $location->lat,
            'player_one_guess_lng' => $location->lng,
            'player_two_guess_lat' => $location->lat + 1,
            'player_two_guess_lng' => $location->lng + 1,
            'started_at' => now()->subMinutes(4),
            'finished_at' => now()->subMinutes(3),
        ]);

        // Round 2: p2 takes lead (cumulative: p1=5000, p2=7000)
        Round::create([
            'location_heading' => 0,
            'game_id' => $game->getKey(),
            'round_number' => 2,
            'location_lat' => $location->lat,
            'location_lng' => $location->lng,
            'player_one_score' => 1000,
            'player_two_score' => 5000,
            'player_one_guess_lat' => $location->lat + 2,
            'player_one_guess_lng' => $location->lng + 2,
            'player_two_guess_lat' => $location->lat,
            'player_two_guess_lng' => $location->lng,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $response = $this->getJson("/games/{$game->getKey()}/report");

        $response->assertOk();
        $response->assertJsonPath('lead_changes', 1);
        $response->assertJsonPath('momentum.0.leader', 'player_one');
        $response->assertJsonPath('momentum.1.leader', 'player_two');
        $response->assertJsonPath('momentum.1.player_one_cumulative', 5000);
        $response->assertJsonPath('momentum.1.player_two_cumulative', 7000);
    }

    public function test_identifies_closest_and_biggest_blowout_rounds(): void
    {
        $this->setupMap();
        $game = Game::factory()->completed()->create();
        $location = $game->map->locations()->first();

        // Close round (margin 100)
        Round::create([
            'location_heading' => 0,
            'game_id' => $game->getKey(),
            'round_number' => 1,
            'location_lat' => $location->lat,
            'location_lng' => $location->lng,
            'player_one_score' => 3050,
            'player_two_score' => 2950,
            'player_one_guess_lat' => $location->lat,
            'player_one_guess_lng' => $location->lng,
            'player_two_guess_lat' => $location->lat + 0.1,
            'player_two_guess_lng' => $location->lng + 0.1,
            'started_at' => now()->subMinutes(4),
            'finished_at' => now()->subMinutes(3),
        ]);

        // Blowout round (margin 4500)
        Round::create([
            'location_heading' => 0,
            'game_id' => $game->getKey(),
            'round_number' => 2,
            'location_lat' => $location->lat,
            'location_lng' => $location->lng,
            'player_one_score' => 5000,
            'player_two_score' => 500,
            'player_one_guess_lat' => $location->lat,
            'player_one_guess_lng' => $location->lng,
            'player_two_guess_lat' => $location->lat + 5,
            'player_two_guess_lng' => $location->lng + 5,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $response = $this->getJson("/games/{$game->getKey()}/report");

        $response->assertOk();
        $response->assertJsonPath('closest_round.round_number', 1);
        $response->assertJsonPath('closest_round.margin', 100);
        $response->assertJsonPath('biggest_blowout.round_number', 2);
        $response->assertJsonPath('biggest_blowout.margin', 4500);
    }

    public function test_detects_comeback(): void
    {
        $this->setupMap();
        $game = Game::factory()->completed()->create();
        $location = $game->map->locations()->first();

        // p2 leads early
        Round::create([
            'location_heading' => 0,
            'game_id' => $game->getKey(),
            'round_number' => 1,
            'location_lat' => $location->lat,
            'location_lng' => $location->lng,
            'player_one_score' => 1000,
            'player_two_score' => 5000,
            'player_one_guess_lat' => $location->lat + 2,
            'player_one_guess_lng' => $location->lng + 2,
            'player_two_guess_lat' => $location->lat,
            'player_two_guess_lng' => $location->lng,
            'started_at' => now()->subMinutes(4),
            'finished_at' => now()->subMinutes(3),
        ]);

        // p1 comes back (cumulative: p1=6000, p2=6000)
        Round::create([
            'location_heading' => 0,
            'game_id' => $game->getKey(),
            'round_number' => 2,
            'location_lat' => $location->lat,
            'location_lng' => $location->lng,
            'player_one_score' => 5000,
            'player_two_score' => 1000,
            'player_one_guess_lat' => $location->lat,
            'player_one_guess_lng' => $location->lng,
            'player_two_guess_lat' => $location->lat + 2,
            'player_two_guess_lng' => $location->lng + 2,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        // Set p1 as winner
        $game->update(['winner_id' => $game->player_one_id]);

        $response = $this->getJson("/games/{$game->getKey()}/report");

        $response->assertOk();
        $response->assertJsonPath('comebacks', 1);
    }

    public function test_empty_game_returns_empty_report(): void
    {
        $this->setupMap();
        $game = Game::factory()->completed()->create();

        $response = $this->getJson("/games/{$game->getKey()}/report");

        $response->assertOk();
        $response->assertJsonPath('rounds', []);
        $response->assertJsonPath('momentum', []);
        $response->assertJsonPath('lead_changes', 0);
        $response->assertJsonPath('comebacks', 0);
    }

    public function test_returns_404_for_invalid_game(): void
    {
        $response = $this->getJson('/games/nonexistent-uuid/report');

        $response->assertNotFound();
    }
}
