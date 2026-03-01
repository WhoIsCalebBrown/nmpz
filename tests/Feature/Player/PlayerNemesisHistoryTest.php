<?php

namespace Tests\Feature\Player;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerNemesisHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_nemesis_for_new_player(): void
    {
        $player = Player::factory()->create();

        $response = $this->getJson("/players/{$player->getKey()}/nemesis");

        $response->assertOk();
        $response->assertJsonPath('nemesis', null);
        $response->assertJsonPath('games', []);
    }

    public function test_identifies_nemesis_and_returns_history(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $nemesis = Player::factory()->create();

        // Player loses 3 of 4 games vs nemesis
        for ($i = 0; $i < 3; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $player->getKey(),
                'player_two_id' => $nemesis->getKey(),
                'map_id' => $map->getKey(),
            ]);
            $game->update(['winner_id' => $nemesis->getKey()]);
        }

        $game = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $nemesis->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $game->update(['winner_id' => $player->getKey()]);

        $response = $this->getJson("/players/{$player->getKey()}/nemesis");

        $response->assertOk();
        $response->assertJsonPath('nemesis.player_id', $nemesis->getKey());
        $response->assertJsonPath('nemesis.total_games', 4);
        $response->assertJsonPath('nemesis.player_wins', 1);
        $response->assertJsonPath('nemesis.nemesis_wins', 3);
        $this->assertEquals(25, $response->json('nemesis.win_rate'));
        $response->assertJsonCount(4, 'games');
    }

    public function test_requires_minimum_3_games_for_nemesis(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        // Only 2 games
        for ($i = 0; $i < 2; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $player->getKey(),
                'player_two_id' => $opponent->getKey(),
                'map_id' => $map->getKey(),
            ]);
            $game->update(['winner_id' => $opponent->getKey()]);
        }

        $response = $this->getJson("/players/{$player->getKey()}/nemesis");

        $response->assertOk();
        $response->assertJsonPath('nemesis', null);
    }

    public function test_nemesis_is_opponent_with_lowest_win_rate(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $easyOpponent = Player::factory()->create();
        $hardOpponent = Player::factory()->create();

        // 3 wins vs easy opponent (100% win rate)
        for ($i = 0; $i < 3; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $player->getKey(),
                'player_two_id' => $easyOpponent->getKey(),
                'map_id' => $map->getKey(),
            ]);
            $game->update(['winner_id' => $player->getKey()]);
        }

        // 0 wins vs hard opponent (0% win rate) — nemesis
        for ($i = 0; $i < 3; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $player->getKey(),
                'player_two_id' => $hardOpponent->getKey(),
                'map_id' => $map->getKey(),
            ]);
            $game->update(['winner_id' => $hardOpponent->getKey()]);
        }

        $response = $this->getJson("/players/{$player->getKey()}/nemesis");

        $response->assertOk();
        $response->assertJsonPath('nemesis.player_id', $hardOpponent->getKey());
        $this->assertEquals(0, $response->json('nemesis.win_rate'));
    }

    public function test_returns_404_for_invalid_player(): void
    {
        $response = $this->getJson('/players/nonexistent-uuid/nemesis');

        $response->assertNotFound();
    }
}
