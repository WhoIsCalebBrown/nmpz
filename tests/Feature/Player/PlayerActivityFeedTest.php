<?php

namespace Tests\Feature\Player;

use App\Models\Achievement;
use App\Models\EloHistory;
use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerAchievement;
use App\Models\Round;
use App\Models\SoloGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerActivityFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_feed_for_new_player(): void
    {
        $player = Player::factory()->create();

        $response = $this->getJson("/players/{$player->getKey()}/activity-feed");

        $response->assertOk();
        $response->assertJsonPath('feed', []);
    }

    public function test_includes_completed_games(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        $game = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);

        $response = $this->getJson("/players/{$p1->getKey()}/activity-feed");

        $response->assertOk();
        $feed = $response->json('feed');
        $this->assertNotEmpty($feed);

        $gameEntry = collect($feed)->firstWhere('type', 'game');
        $this->assertNotNull($gameEntry);
        $this->assertEquals($game->getKey(), $gameEntry['game_id']);
        $this->assertContains($gameEntry['result'], ['win', 'loss', 'draw']);
    }

    public function test_includes_elo_changes(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        $game = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);

        EloHistory::factory()->create([
            'player_id' => $p1->getKey(),
            'game_id' => $game->getKey(),
            'rating_before' => 1000,
            'rating_after' => 1025,
            'rating_change' => 25,
        ]);

        $response = $this->getJson("/players/{$p1->getKey()}/activity-feed");

        $response->assertOk();
        $feed = $response->json('feed');
        $eloEntry = collect($feed)->firstWhere('type', 'elo_change');
        $this->assertNotNull($eloEntry);
        $this->assertEquals(25, $eloEntry['rating_change']);
        $this->assertEquals(1025, $eloEntry['rating_after']);
    }

    public function test_includes_achievements(): void
    {
        $player = Player::factory()->create();

        $achievement = Achievement::create([
            'key' => 'first_win',
            'name' => 'First Win',
            'description' => 'Win your first game',
            'icon' => 'trophy',
        ]);

        PlayerAchievement::create([
            'player_id' => $player->getKey(),
            'achievement_id' => $achievement->getKey(),
            'earned_at' => now(),
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/activity-feed");

        $response->assertOk();
        $feed = $response->json('feed');
        $achievementEntry = collect($feed)->firstWhere('type', 'achievement');
        $this->assertNotNull($achievementEntry);
        $this->assertEquals('first_win', $achievementEntry['key']);
        $this->assertEquals('First Win', $achievementEntry['name']);
    }

    public function test_includes_solo_games(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->create();

        SoloGame::factory()->completed()->create([
            'player_id' => $player->getKey(),
            'map_id' => $map->getKey(),
            'total_score' => 15000,
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/activity-feed");

        $response->assertOk();
        $feed = $response->json('feed');
        $soloEntry = collect($feed)->firstWhere('type', 'solo_game');
        $this->assertNotNull($soloEntry);
        $this->assertEquals(15000, $soloEntry['total_score']);
    }

    public function test_feed_is_sorted_by_timestamp_descending(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        $game = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);
        // Force older timestamp
        $game->update(['updated_at' => now()->subDays(5)]);

        // Newer solo game
        SoloGame::factory()->completed()->create([
            'player_id' => $p1->getKey(),
            'map_id' => $map->getKey(),
            'completed_at' => now()->subDay(),
        ]);

        $response = $this->getJson("/players/{$p1->getKey()}/activity-feed");

        $response->assertOk();
        $feed = $response->json('feed');
        $this->assertGreaterThanOrEqual(2, count($feed));

        // Timestamps should be in descending order
        $timestamps = array_column($feed, 'timestamp');
        $sorted = $timestamps;
        rsort($sorted);
        $this->assertEquals($sorted, $timestamps);
    }

    public function test_feed_is_limited_to_20_items(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // Create 25 games
        for ($i = 0; $i < 25; $i++) {
            Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map->getKey(),
            ]);
        }

        $response = $this->getJson("/players/{$p1->getKey()}/activity-feed");

        $response->assertOk();
        $feed = $response->json('feed');
        // Each source is limited to 10, merged takes top 20
        $this->assertLessThanOrEqual(20, count($feed));
    }

    public function test_unknown_player_returns_404(): void
    {
        $response = $this->getJson('/players/nonexistent-uuid/activity-feed');

        $response->assertNotFound();
    }
}
