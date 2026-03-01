<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerStreaksTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_streaks_for_new_player(): void
    {
        $player = Player::factory()->create();

        $response = $this->getJson("/players/{$player->getKey()}/streaks");

        $response->assertOk();
        $response->assertJsonPath('current_streak', 0);
        $response->assertJsonPath('best_streak', 0);
        $response->assertJsonPath('notable_streaks', []);
    }

    public function test_tracks_current_win_streak(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // 3 consecutive wins
        for ($i = 0; $i < 3; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map->getKey(),
                'updated_at' => now()->subMinutes(10 - $i),
            ]);
            $game->update(['winner_id' => $p1->getKey()]);
        }

        $response = $this->getJson("/players/{$p1->getKey()}/streaks");

        $response->assertOk();
        $response->assertJsonPath('current_streak', 3);
    }

    public function test_streak_broken_by_loss(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // 2 wins, 1 loss, 1 win
        for ($i = 0; $i < 4; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map->getKey(),
                'updated_at' => now()->subMinutes(10 - $i),
            ]);
            $winnerId = ($i === 2) ? $p2->getKey() : $p1->getKey();
            $game->update(['winner_id' => $winnerId]);
        }

        $response = $this->getJson("/players/{$p1->getKey()}/streaks");

        $response->assertOk();
        // Current streak is 1 (last game was a win)
        $response->assertJsonPath('current_streak', 1);
    }

    public function test_returns_notable_streaks(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        PlayerStats::factory()->create([
            'player_id' => $p1->getKey(),
            'best_win_streak' => 5,
        ]);

        // 3 wins, then a loss, then 2 wins
        for ($i = 0; $i < 6; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map->getKey(),
                'updated_at' => now()->subMinutes(20 - $i),
            ]);
            $winnerId = ($i === 3) ? $p2->getKey() : $p1->getKey();
            $game->update(['winner_id' => $winnerId]);
        }

        $response = $this->getJson("/players/{$p1->getKey()}/streaks");

        $response->assertOk();
        $response->assertJsonPath('best_streak', 5);

        $streaks = $response->json('notable_streaks');
        $this->assertNotEmpty($streaks);
        // First streak should be the longest (3 wins)
        $this->assertEquals(3, $streaks[0]['length']);
        // Second streak (2 wins)
        $this->assertEquals(2, $streaks[1]['length']);
    }

    public function test_streak_includes_game_ids(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        $gameIds = [];
        for ($i = 0; $i < 3; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map->getKey(),
                'updated_at' => now()->subMinutes(10 - $i),
            ]);
            $game->update(['winner_id' => $p1->getKey()]);
            $gameIds[] = $game->getKey();
        }

        $response = $this->getJson("/players/{$p1->getKey()}/streaks");

        $response->assertOk();
        $streaks = $response->json('notable_streaks');
        $this->assertNotEmpty($streaks);
        $this->assertEquals($gameIds, $streaks[0]['game_ids']);
    }

    public function test_unknown_player_returns_404(): void
    {
        $response = $this->getJson('/players/nonexistent-uuid/streaks');

        $response->assertNotFound();
    }
}
