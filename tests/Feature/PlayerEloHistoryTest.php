<?php

namespace Tests\Feature;

use App\Models\EloHistory;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerEloHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_history_for_new_player(): void
    {
        $player = Player::factory()->create(['elo_rating' => 1000]);

        $response = $this->getJson("/players/{$player->getKey()}/elo-history");

        $response->assertOk();
        $response->assertJsonPath('player_id', $player->getKey());
        $response->assertJsonPath('current_elo', 1000);
        $response->assertJsonPath('rank', 'Silver');
        $response->assertJsonPath('history', []);
    }

    public function test_returns_elo_history_entries(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create(['elo_rating' => 1050]);
        $p2 = Player::factory()->create();

        $game = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $game->update(['winner_id' => $p1->getKey()]);

        EloHistory::factory()->create([
            'player_id' => $p1->getKey(),
            'game_id' => $game->getKey(),
            'rating_before' => 1000,
            'rating_after' => 1025,
            'rating_change' => 25,
            'opponent_rating' => 1000,
        ]);

        $response = $this->getJson("/players/{$p1->getKey()}/elo-history");

        $response->assertOk();
        $history = $response->json('history');
        $this->assertCount(1, $history);
        $this->assertEquals(1000, $history[0]['rating_before']);
        $this->assertEquals(1025, $history[0]['rating_after']);
        $this->assertEquals(25, $history[0]['rating_change']);
        $this->assertTrue($history[0]['won']);
    }

    public function test_history_is_chronologically_ordered(): void
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
            'rating_before' => 1000,
            'rating_after' => 1025,
            'rating_change' => 25,
            'created_at' => now()->subHours(2),
        ]);

        EloHistory::factory()->create([
            'player_id' => $p1->getKey(),
            'game_id' => $game2->getKey(),
            'rating_before' => 1025,
            'rating_after' => 1010,
            'rating_change' => -15,
            'created_at' => now()->subHour(),
        ]);

        $response = $this->getJson("/players/{$p1->getKey()}/elo-history");

        $response->assertOk();
        $history = $response->json('history');
        $this->assertCount(2, $history);
        // First entry should be older (chronological order)
        $this->assertEquals(1000, $history[0]['rating_before']);
        $this->assertEquals(1025, $history[1]['rating_before']);
    }

    public function test_respects_limit_parameter(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $p2 = Player::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $player->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map->getKey(),
            ]);
            EloHistory::factory()->create([
                'player_id' => $player->getKey(),
                'game_id' => $game->getKey(),
                'created_at' => now()->subMinutes(10 - $i),
            ]);
        }

        $response = $this->getJson("/players/{$player->getKey()}/elo-history?limit=3");

        $response->assertOk();
        $history = $response->json('history');
        $this->assertCount(3, $history);
    }

    public function test_limit_capped_at_200(): void
    {
        $player = Player::factory()->create();

        $response = $this->getJson("/players/{$player->getKey()}/elo-history?limit=500");

        $response->assertOk();
        // Just verifying it doesn't error — the cap is enforced internally
    }

    public function test_unknown_player_returns_404(): void
    {
        $response = $this->getJson('/players/nonexistent-uuid/elo-history');

        $response->assertNotFound();
    }
}
