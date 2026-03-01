<?php

namespace Tests\Feature\Player;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerRivalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_rivals_for_new_player(): void
    {
        $player = Player::factory()->create();

        $response = $this->getJson("/players/{$player->getKey()}/rivals");

        $response->assertOk();
        $response->assertJsonPath('most_played', null);
        $response->assertJsonPath('nemesis', null);
        $response->assertJsonPath('best_matchup', null);
    }

    public function test_identifies_most_played_opponent(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();
        $p3 = Player::factory()->create();

        // 3 games vs p2
        for ($i = 0; $i < 3; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map->getKey(),
            ]);
            $game->update(['winner_id' => $p1->getKey()]);
        }

        // 1 game vs p3
        $game = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p3->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $game->update(['winner_id' => $p1->getKey()]);

        $response = $this->getJson("/players/{$p1->getKey()}/rivals");

        $response->assertOk();
        $this->assertEquals($p2->getKey(), $response->json('most_played.player_id'));
        $this->assertEquals(3, $response->json('most_played.games_played'));
    }

    public function test_identifies_nemesis(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();
        $p3 = Player::factory()->create();

        // p1 beats p2 both times
        for ($i = 0; $i < 2; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map->getKey(),
            ]);
            $game->update(['winner_id' => $p1->getKey()]);
        }

        // p3 beats p1 both times (nemesis)
        for ($i = 0; $i < 2; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p3->getKey(),
                'map_id' => $map->getKey(),
            ]);
            $game->update(['winner_id' => $p3->getKey()]);
        }

        $response = $this->getJson("/players/{$p1->getKey()}/rivals");

        $response->assertOk();
        // Nemesis is p3 (0% win rate)
        $this->assertEquals($p3->getKey(), $response->json('nemesis.player_id'));
        $this->assertEquals(0, $response->json('nemesis.wins'));
        $this->assertEquals(2, $response->json('nemesis.losses'));
    }

    public function test_identifies_best_matchup(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();
        $p3 = Player::factory()->create();

        // p1 beats p2 both times (100% win rate)
        for ($i = 0; $i < 2; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map->getKey(),
            ]);
            $game->update(['winner_id' => $p1->getKey()]);
        }

        // p1 loses to p3 both times
        for ($i = 0; $i < 2; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p3->getKey(),
                'map_id' => $map->getKey(),
            ]);
            $game->update(['winner_id' => $p3->getKey()]);
        }

        $response = $this->getJson("/players/{$p1->getKey()}/rivals");

        $response->assertOk();
        // Best matchup is p2 (100% win rate)
        $this->assertEquals($p2->getKey(), $response->json('best_matchup.player_id'));
        $this->assertEquals(100.0, $response->json('best_matchup.win_rate'));
    }

    public function test_requires_minimum_two_games_for_nemesis(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // Only 1 game vs p2
        $game = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $game->update(['winner_id' => $p2->getKey()]);

        $response = $this->getJson("/players/{$p1->getKey()}/rivals");

        $response->assertOk();
        // Not enough games for nemesis
        $this->assertNull($response->json('nemesis'));
        // But most_played still works
        $this->assertEquals($p2->getKey(), $response->json('most_played.player_id'));
    }

    public function test_unknown_player_returns_404(): void
    {
        $response = $this->getJson('/players/nonexistent-uuid/rivals');

        $response->assertNotFound();
    }
}
