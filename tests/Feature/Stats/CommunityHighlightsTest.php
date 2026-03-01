<?php

namespace Tests\Feature\Stats;

use App\Models\EloHistory;
use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerStats;
use App\Models\Round;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CommunityHighlightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_highlights_when_no_data(): void
    {
        $response = $this->getJson('/community/highlights');

        $response->assertOk();
        $response->assertJsonPath('play_of_the_day', null);
        $response->assertJsonPath('rising_stars', []);
        $response->assertJsonPath('hottest_rivalry', null);
    }

    public function test_play_of_the_day_returns_best_round_score_today(): void
    {
        Carbon::setTestNow(now());
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

        $response = $this->getJson('/community/highlights');

        $response->assertOk();
        $response->assertJsonPath('play_of_the_day.score', 5000);
        $response->assertJsonPath('play_of_the_day.game_id', $game->getKey());
        $response->assertJsonPath('play_of_the_day.player_id', $game->player_one_id);

        Carbon::setTestNow();
    }

    public function test_rising_stars_returns_new_players_with_elo_gains(): void
    {
        Carbon::setTestNow(now());
        $map = $this->setupMap();
        $newPlayer = Player::factory()->withElo(1100)->create();
        PlayerStats::create([
            'player_id' => $newPlayer->getKey(),
            'games_played' => 10,
        ]);

        $opponent = Player::factory()->create();
        $game = Game::factory()->completed()->create([
            'player_one_id' => $newPlayer->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $game->update(['winner_id' => $newPlayer->getKey()]);

        EloHistory::create([
            'player_id' => $newPlayer->getKey(),
            'game_id' => $game->getKey(),
            'rating_before' => 1000,
            'rating_after' => 1100,
            'rating_change' => 100,
            'opponent_rating' => 1000,
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->getJson('/community/highlights');

        $response->assertOk();
        $this->assertCount(1, $response->json('rising_stars'));
        $response->assertJsonPath('rising_stars.0.player_id', $newPlayer->getKey());
        $response->assertJsonPath('rising_stars.0.elo_change', 100);

        Carbon::setTestNow();
    }

    public function test_hottest_rivalry_finds_most_rematched_pair(): void
    {
        Carbon::setTestNow(now());
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // 4 games between p1 and p2
        for ($i = 0; $i < 4; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map->getKey(),
                'created_at' => now()->subDays(3),
            ]);
            $game->update(['winner_id' => $i < 3 ? $p1->getKey() : $p2->getKey()]);
        }

        $response = $this->getJson('/community/highlights');

        $response->assertOk();
        $this->assertNotNull($response->json('hottest_rivalry'));
        $response->assertJsonPath('hottest_rivalry.total_games', 4);

        Carbon::setTestNow();
    }

    public function test_no_rivalry_with_single_game(): void
    {
        Carbon::setTestNow(now());
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        $game = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
            'created_at' => now()->subDays(1),
        ]);
        $game->update(['winner_id' => $p1->getKey()]);

        $response = $this->getJson('/community/highlights');

        $response->assertOk();
        $response->assertJsonPath('hottest_rivalry', null);

        Carbon::setTestNow();
    }

    public function test_rising_stars_excludes_experienced_players(): void
    {
        Carbon::setTestNow(now());
        $map = $this->setupMap();
        $veteran = Player::factory()->withElo(1500)->create();
        PlayerStats::create([
            'player_id' => $veteran->getKey(),
            'games_played' => 100,
        ]);

        $opponent = Player::factory()->create();
        $game = Game::factory()->completed()->create([
            'player_one_id' => $veteran->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
        ]);

        EloHistory::create([
            'player_id' => $veteran->getKey(),
            'game_id' => $game->getKey(),
            'rating_before' => 1400,
            'rating_after' => 1500,
            'rating_change' => 100,
            'opponent_rating' => 1400,
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->getJson('/community/highlights');

        $response->assertOk();
        $this->assertCount(0, $response->json('rising_stars'));

        Carbon::setTestNow();
    }
}
