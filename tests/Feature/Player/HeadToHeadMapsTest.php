<?php

namespace Tests\Feature\Player;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeadToHeadMapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_maps_for_no_games(): void
    {
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        $response = $this->getJson("/players/{$player->getKey()}/head-to-head/{$opponent->getKey()}/maps");

        $response->assertOk();
        $response->assertJsonPath('maps', []);
    }

    public function test_returns_map_breakdown(): void
    {
        $map1 = $this->setupMap(10, ['name' => 'map-one', 'display_name' => 'Map One']);
        $map2 = $this->setupMap(10, ['name' => 'map-two', 'display_name' => 'Map Two']);
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        // 2 games on map1: player wins both
        for ($i = 0; $i < 2; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $player->getKey(),
                'player_two_id' => $opponent->getKey(),
                'map_id' => $map1->getKey(),
            ]);
            $game->update(['winner_id' => $player->getKey()]);
        }

        // 1 game on map2: opponent wins
        $game = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map2->getKey(),
        ]);
        $game->update(['winner_id' => $opponent->getKey()]);

        $response = $this->getJson("/players/{$player->getKey()}/head-to-head/{$opponent->getKey()}/maps");

        $response->assertOk();
        $response->assertJsonCount(2, 'maps');

        // Sorted by most games first
        $response->assertJsonPath('maps.0.map_name', 'Map One');
        $response->assertJsonPath('maps.0.total_games', 2);
        $response->assertJsonPath('maps.0.player_wins', 2);
        $response->assertJsonPath('maps.0.opponent_wins', 0);
        $this->assertEquals(100, $response->json('maps.0.player_win_rate'));

        $response->assertJsonPath('maps.1.map_name', 'Map Two');
        $response->assertJsonPath('maps.1.total_games', 1);
        $response->assertJsonPath('maps.1.player_wins', 0);
        $response->assertJsonPath('maps.1.opponent_wins', 1);
        $this->assertEquals(0, $response->json('maps.1.player_win_rate'));
    }

    public function test_works_regardless_of_player_order(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        // Player is player_two
        $game = Game::factory()->completed()->create([
            'player_one_id' => $opponent->getKey(),
            'player_two_id' => $player->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $game->update(['winner_id' => $player->getKey()]);

        $response = $this->getJson("/players/{$player->getKey()}/head-to-head/{$opponent->getKey()}/maps");

        $response->assertOk();
        $response->assertJsonCount(1, 'maps');
        $response->assertJsonPath('maps.0.player_wins', 1);
    }

    public function test_returns_404_for_invalid_player(): void
    {
        $player = Player::factory()->create();

        $response = $this->getJson("/players/{$player->getKey()}/head-to-head/nonexistent-uuid/maps");

        $response->assertNotFound();
    }
}
