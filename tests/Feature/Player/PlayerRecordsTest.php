<?php

namespace Tests\Feature\Player;

use App\Models\EloHistory;
use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerStats;
use App\Models\Round;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_records_for_player(): void
    {
        $player = Player::factory()->create();

        $response = $this->getJson("/players/{$player->getKey()}/records");

        $response->assertOk();
        $response->assertJsonStructure([
            'player_id',
            'records' => [
                'best_round_score',
                'perfect_rounds',
                'biggest_elo_gain',
                'biggest_elo_loss',
                'longest_game_rounds',
                'total_games',
                'total_wins',
                'best_win_streak',
            ],
        ]);
    }

    public function test_tracks_best_round_score(): void
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
            'location_lat' => 0, 'location_lng' => 0, 'location_heading' => 0,
            'player_one_score' => 4500,
            'player_two_score' => 3000,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $response = $this->getJson("/players/{$p1->getKey()}/records");

        $response->assertOk();
        $this->assertEquals(4500, $response->json('records.best_round_score.score'));
        $this->assertEquals($game->getKey(), $response->json('records.best_round_score.game_id'));
    }

    public function test_counts_perfect_rounds(): void
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
            'location_lat' => 0, 'location_lng' => 0, 'location_heading' => 0,
            'player_one_score' => 5000,
            'player_two_score' => 3000,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        Round::create([
            'game_id' => $game->getKey(),
            'round_number' => 2,
            'location_lat' => 0, 'location_lng' => 0, 'location_heading' => 0,
            'player_one_score' => 5000,
            'player_two_score' => 4000,
            'started_at' => now()->subMinutes(4),
            'finished_at' => now()->subMinutes(3),
        ]);

        $response = $this->getJson("/players/{$p1->getKey()}/records");

        $response->assertOk();
        $this->assertEquals(2, $response->json('records.perfect_rounds'));
    }

    public function test_tracks_biggest_elo_changes(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        $game1 = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);

        $game2 = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);

        EloHistory::factory()->create([
            'player_id' => $p1->getKey(),
            'game_id' => $game1->getKey(),
            'rating_change' => 30,
            'rating_after' => 1030,
        ]);

        EloHistory::factory()->create([
            'player_id' => $p1->getKey(),
            'game_id' => $game2->getKey(),
            'rating_change' => -20,
            'rating_after' => 1010,
        ]);

        $response = $this->getJson("/players/{$p1->getKey()}/records");

        $response->assertOk();
        $this->assertEquals(30, $response->json('records.biggest_elo_gain.change'));
        $this->assertEquals(-20, $response->json('records.biggest_elo_loss.change'));
    }

    public function test_tracks_longest_game(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        $game = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);

        for ($i = 1; $i <= 5; $i++) {
            Round::create([
                'game_id' => $game->getKey(),
                'round_number' => $i,
                'location_lat' => 0, 'location_lng' => 0, 'location_heading' => 0,
                'player_one_score' => 3000,
                'player_two_score' => 3000,
                'started_at' => now()->subMinutes($i * 2),
                'finished_at' => now()->subMinutes($i * 2 - 1),
            ]);
        }

        $response = $this->getJson("/players/{$p1->getKey()}/records");

        $response->assertOk();
        $this->assertEquals(5, $response->json('records.longest_game_rounds.rounds'));
    }

    public function test_includes_stats_records(): void
    {
        $player = Player::factory()->create();
        PlayerStats::factory()->create([
            'player_id' => $player->getKey(),
            'games_played' => 50,
            'games_won' => 30,
            'best_win_streak' => 8,
            'closest_guess_km' => 0.5,
            'total_damage_dealt' => 25000,
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/records");

        $response->assertOk();
        $this->assertEquals(50, $response->json('records.total_games'));
        $this->assertEquals(30, $response->json('records.total_wins'));
        $this->assertEquals(8, $response->json('records.best_win_streak'));
    }

    public function test_unknown_player_returns_404(): void
    {
        $response = $this->getJson('/players/nonexistent-uuid/records');

        $response->assertNotFound();
    }
}
