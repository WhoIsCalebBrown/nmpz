<?php

namespace Tests\Feature\Queue;

use App\Models\EloHistory;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchmakingStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_stats_when_no_games(): void
    {
        $response = $this->getJson('/matchmaking/stats');

        $response->assertOk();
        $response->assertJsonPath('total_games', 0);
        $response->assertJsonPath('average_elo_gap', 0);
        $response->assertJsonPath('upset_rate', 0);
        $response->assertJsonPath('balance_score', 0);
    }

    public function test_calculates_average_elo_gap(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->withElo(1200)->create();
        $p2 = Player::factory()->withElo(1000)->create();

        $game = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $game->update(['winner_id' => $p1->getKey()]);

        // Create elo_history entries
        EloHistory::create([
            'player_id' => $p1->getKey(),
            'game_id' => $game->getKey(),
            'rating_before' => 1200,
            'rating_after' => 1215,
            'rating_change' => 15,
            'opponent_rating' => 1000,
        ]);
        EloHistory::create([
            'player_id' => $p2->getKey(),
            'game_id' => $game->getKey(),
            'rating_before' => 1000,
            'rating_after' => 985,
            'rating_change' => -15,
            'opponent_rating' => 1200,
        ]);

        $response = $this->getJson('/matchmaking/stats');

        $response->assertOk();
        $response->assertJsonPath('total_games', 1);
        $this->assertEquals(200, $response->json('average_elo_gap'));
        $this->assertEquals(200, $response->json('median_elo_gap'));
    }

    public function test_calculates_upset_rate(): void
    {
        $map = $this->setupMap();
        $strong = Player::factory()->withElo(1500)->create();
        $weak = Player::factory()->withElo(1000)->create();

        // Upset: weak player wins
        $game = Game::factory()->completed()->create([
            'player_one_id' => $strong->getKey(),
            'player_two_id' => $weak->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $game->update(['winner_id' => $weak->getKey()]);

        EloHistory::create([
            'player_id' => $strong->getKey(),
            'game_id' => $game->getKey(),
            'rating_before' => 1500,
            'rating_after' => 1475,
            'rating_change' => -25,
            'opponent_rating' => 1000,
        ]);
        EloHistory::create([
            'player_id' => $weak->getKey(),
            'game_id' => $game->getKey(),
            'rating_before' => 1000,
            'rating_after' => 1025,
            'rating_change' => 25,
            'opponent_rating' => 1500,
        ]);

        $response = $this->getJson('/matchmaking/stats');

        $response->assertOk();
        $this->assertEquals(100, $response->json('upset_rate'));
    }

    public function test_calculates_gap_distribution(): void
    {
        $map = $this->setupMap();

        // Close match (gap 30)
        $p1 = Player::factory()->withElo(1015)->create();
        $p2 = Player::factory()->withElo(985)->create();
        $game1 = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $game1->update(['winner_id' => $p1->getKey()]);
        EloHistory::create([
            'player_id' => $p1->getKey(),
            'game_id' => $game1->getKey(),
            'rating_before' => 1015,
            'rating_after' => 1030,
            'rating_change' => 15,
            'opponent_rating' => 985,
        ]);
        EloHistory::create([
            'player_id' => $p2->getKey(),
            'game_id' => $game1->getKey(),
            'rating_before' => 985,
            'rating_after' => 970,
            'rating_change' => -15,
            'opponent_rating' => 1015,
        ]);

        // Wide gap match (gap 600)
        $p3 = Player::factory()->withElo(1800)->create();
        $p4 = Player::factory()->withElo(1200)->create();
        $game2 = Game::factory()->completed()->create([
            'player_one_id' => $p3->getKey(),
            'player_two_id' => $p4->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $game2->update(['winner_id' => $p3->getKey()]);
        EloHistory::create([
            'player_id' => $p3->getKey(),
            'game_id' => $game2->getKey(),
            'rating_before' => 1800,
            'rating_after' => 1810,
            'rating_change' => 10,
            'opponent_rating' => 1200,
        ]);
        EloHistory::create([
            'player_id' => $p4->getKey(),
            'game_id' => $game2->getKey(),
            'rating_before' => 1200,
            'rating_after' => 1190,
            'rating_change' => -10,
            'opponent_rating' => 1800,
        ]);

        $response = $this->getJson('/matchmaking/stats');

        $response->assertOk();
        $response->assertJsonPath('total_games', 2);
        $response->assertJsonPath('gap_distribution.0-50', 1);
        $response->assertJsonPath('gap_distribution.500+', 1);
    }

    public function test_balance_score_decreases_with_large_gaps(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->withElo(2000)->create();
        $p2 = Player::factory()->withElo(1000)->create();

        $game = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $game->update(['winner_id' => $p1->getKey()]);

        EloHistory::create([
            'player_id' => $p1->getKey(),
            'game_id' => $game->getKey(),
            'rating_before' => 2000,
            'rating_after' => 2005,
            'rating_change' => 5,
            'opponent_rating' => 1000,
        ]);
        EloHistory::create([
            'player_id' => $p2->getKey(),
            'game_id' => $game->getKey(),
            'rating_before' => 1000,
            'rating_after' => 995,
            'rating_change' => -5,
            'opponent_rating' => 2000,
        ]);

        $response = $this->getJson('/matchmaking/stats');

        $response->assertOk();
        // 1000 gap -> balance_score = max(0, 100 - 1000/5) = 0
        $this->assertEquals(0, $response->json('balance_score'));
    }
}
