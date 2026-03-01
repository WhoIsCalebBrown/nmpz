<?php

namespace Tests\Feature\Player;

use App\Models\EloHistory;
use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_insights_for_new_player(): void
    {
        $player = Player::factory()->create();

        $response = $this->getJson("/players/{$player->getKey()}/insights");

        $response->assertOk();
        $response->assertJsonPath('insights', []);
    }

    public function test_returns_empty_insights_with_fewer_than_3_games(): void
    {
        $player = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $player->getKey(),
            'games_played' => 2,
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/insights");

        $response->assertOk();
        $response->assertJsonPath('insights', []);
    }

    public function test_positive_insight_for_win_streak(): void
    {
        $player = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $player->getKey(),
            'games_played' => 10,
            'current_win_streak' => 5,
            'total_rounds' => 30,
            'total_score' => 90000,
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/insights");

        $response->assertOk();
        $streakInsight = collect($response->json('insights'))->firstWhere('category', 'streak');
        $this->assertNotNull($streakInsight);
        $this->assertEquals('positive', $streakInsight['type']);
        $this->assertStringContains('5-game win streak', $streakInsight['message']);
    }

    public function test_accuracy_tip_for_low_scores(): void
    {
        $player = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $player->getKey(),
            'games_played' => 10,
            'total_rounds' => 20,
            'total_score' => 40000,
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/insights");

        $response->assertOk();
        $accuracyInsight = collect($response->json('insights'))->firstWhere('category', 'accuracy');
        $this->assertNotNull($accuracyInsight);
        $this->assertEquals('tip', $accuracyInsight['type']);
    }

    public function test_positive_insight_for_high_accuracy(): void
    {
        $player = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $player->getKey(),
            'games_played' => 10,
            'total_rounds' => 20,
            'total_score' => 90000,
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/insights");

        $response->assertOk();
        $accuracyInsight = collect($response->json('insights'))->firstWhere('category', 'accuracy');
        $this->assertNotNull($accuracyInsight);
        $this->assertEquals('positive', $accuracyInsight['type']);
    }

    public function test_elo_drop_tip(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        PlayerStats::create([
            'player_id' => $player->getKey(),
            'games_played' => 10,
            'total_rounds' => 30,
            'total_score' => 90000,
        ]);

        // 5 losses with large ELO drops
        for ($i = 0; $i < 5; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $player->getKey(),
                'player_two_id' => $opponent->getKey(),
                'map_id' => $map->getKey(),
            ]);
            EloHistory::create([
                'player_id' => $player->getKey(),
                'game_id' => $game->getKey(),
                'rating_before' => 1000 - ($i * 15),
                'rating_after' => 1000 - (($i + 1) * 15),
                'rating_change' => -15,
                'opponent_rating' => 1000,
            ]);
        }

        $response = $this->getJson("/players/{$player->getKey()}/insights");

        $response->assertOk();
        $eloInsight = collect($response->json('insights'))->firstWhere('category', 'elo');
        $this->assertNotNull($eloInsight);
        $this->assertEquals('tip', $eloInsight['type']);
    }

    public function test_precision_insight_for_close_guess(): void
    {
        $player = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $player->getKey(),
            'games_played' => 5,
            'total_rounds' => 15,
            'total_score' => 60000,
            'closest_guess_km' => 0.05,
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/insights");

        $response->assertOk();
        $precisionInsight = collect($response->json('insights'))->firstWhere('category', 'precision');
        $this->assertNotNull($precisionInsight);
        $this->assertEquals('positive', $precisionInsight['type']);
    }

    public function test_map_performance_tip(): void
    {
        $map = $this->setupMap(10, ['display_name' => 'Hard Map']);
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        PlayerStats::create([
            'player_id' => $player->getKey(),
            'games_played' => 10,
            'total_rounds' => 30,
            'total_score' => 90000,
        ]);

        // 4 losses on same map (0% win rate)
        for ($i = 0; $i < 4; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $player->getKey(),
                'player_two_id' => $opponent->getKey(),
                'map_id' => $map->getKey(),
            ]);
            $game->update(['winner_id' => $opponent->getKey()]);
        }

        $response = $this->getJson("/players/{$player->getKey()}/insights");

        $response->assertOk();
        $mapInsight = collect($response->json('insights'))->firstWhere('category', 'map');
        $this->assertNotNull($mapInsight);
        $this->assertEquals('tip', $mapInsight['type']);
        $this->assertStringContains('Hard Map', $mapInsight['message']);
    }

    public function test_returns_404_for_invalid_player(): void
    {
        $response = $this->getJson('/players/nonexistent-uuid/insights');

        $response->assertNotFound();
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }
}
