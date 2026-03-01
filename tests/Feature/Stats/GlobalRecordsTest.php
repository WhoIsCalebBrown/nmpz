<?php

namespace Tests\Feature\Stats;

use App\Models\EloHistory;
use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerStats;
use App\Models\Round;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalRecordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_records_when_no_data(): void
    {
        $response = $this->getJson('/stats/records');

        $response->assertOk();
        $response->assertJsonPath('longest_win_streak', null);
        $response->assertJsonPath('highest_elo', null);
        $response->assertJsonPath('most_perfect_rounds', null);
        $response->assertJsonPath('most_games_played', null);
        $response->assertJsonPath('closest_guess', null);
        $response->assertJsonPath('highest_single_round_score', null);
    }

    public function test_returns_longest_win_streak(): void
    {
        $player = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $player->getKey(),
            'best_win_streak' => 12,
            'games_played' => 20,
        ]);

        $other = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $other->getKey(),
            'best_win_streak' => 5,
            'games_played' => 10,
        ]);

        $response = $this->getJson('/stats/records');

        $response->assertOk();
        $response->assertJsonPath('longest_win_streak.player_id', $player->getKey());
        $response->assertJsonPath('longest_win_streak.value', 12);
    }

    public function test_returns_highest_elo_ever(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->withElo(2100)->create();
        $opponent = Player::factory()->create();

        $game = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
        ]);

        EloHistory::create([
            'player_id' => $player->getKey(),
            'game_id' => $game->getKey(),
            'rating_before' => 2050,
            'rating_after' => 2100,
            'rating_change' => 50,
            'opponent_rating' => 1800,
        ]);

        $response = $this->getJson('/stats/records');

        $response->assertOk();
        $response->assertJsonPath('highest_elo.player_id', $player->getKey());
        $response->assertJsonPath('highest_elo.value', 2100);
    }

    public function test_returns_most_perfect_rounds(): void
    {
        $player = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $player->getKey(),
            'perfect_rounds' => 50,
            'games_played' => 100,
        ]);

        $response = $this->getJson('/stats/records');

        $response->assertOk();
        $response->assertJsonPath('most_perfect_rounds.player_id', $player->getKey());
        $response->assertJsonPath('most_perfect_rounds.value', 50);
    }

    public function test_returns_closest_guess(): void
    {
        $player = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $player->getKey(),
            'closest_guess_km' => 0.023,
            'games_played' => 10,
        ]);

        $response = $this->getJson('/stats/records');

        $response->assertOk();
        $response->assertJsonPath('closest_guess.player_id', $player->getKey());
        $response->assertJsonPath('closest_guess.value', 0.023);
    }

    public function test_returns_highest_single_round_score(): void
    {
        $map = $this->setupMap();
        $game = Game::factory()->completed()->create(['map_id' => $map->getKey()]);
        $location = $map->locations()->first();

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
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $response = $this->getJson('/stats/records');

        $response->assertOk();
        $response->assertJsonPath('highest_single_round_score.value', 5000);
        $response->assertJsonPath('highest_single_round_score.player_id', $game->player_one_id);
    }

    public function test_returns_most_games_played(): void
    {
        $player = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $player->getKey(),
            'games_played' => 200,
        ]);

        $response = $this->getJson('/stats/records');

        $response->assertOk();
        $response->assertJsonPath('most_games_played.player_id', $player->getKey());
        $response->assertJsonPath('most_games_played.value', 200);
    }
}
