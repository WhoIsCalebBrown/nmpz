<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Map;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerFavoriteMapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_for_new_player(): void
    {
        $player = Player::factory()->create();

        $response = $this->getJson("/players/{$player->getKey()}/favorite-maps");

        $response->assertOk();
        $response->assertJsonPath('most_played', null);
        $response->assertJsonPath('best_win_rate', null);
        $response->assertJsonPath('all_maps', []);
    }

    public function test_identifies_most_played_map(): void
    {
        $map1 = $this->setupMap();
        $map2 = $this->setupMap(attributes: ['name' => 'other-map']);
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // 3 games on map1
        for ($i = 0; $i < 3; $i++) {
            Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map1->getKey(),
            ]);
        }

        // 1 game on map2
        Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map2->getKey(),
        ]);

        $response = $this->getJson("/players/{$p1->getKey()}/favorite-maps");

        $response->assertOk();
        $this->assertEquals($map1->getKey(), $response->json('most_played.map_id'));
        $this->assertEquals(3, $response->json('most_played.games_played'));
    }

    public function test_identifies_best_win_rate_map(): void
    {
        $map1 = $this->setupMap();
        $map2 = $this->setupMap(attributes: ['name' => 'other-map']);
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // 3 games on map1, win 1
        for ($i = 0; $i < 3; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map1->getKey(),
            ]);
            $game->update(['winner_id' => $i === 0 ? $p1->getKey() : $p2->getKey()]);
        }

        // 3 games on map2, win all
        for ($i = 0; $i < 3; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map2->getKey(),
            ]);
            $game->update(['winner_id' => $p1->getKey()]);
        }

        $response = $this->getJson("/players/{$p1->getKey()}/favorite-maps");

        $response->assertOk();
        $this->assertEquals($map2->getKey(), $response->json('best_win_rate.map_id'));
        $this->assertEquals(100.0, $response->json('best_win_rate.win_rate'));
    }

    public function test_requires_minimum_games_for_best_win_rate(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // Only 2 games on map (below minimum of 3)
        for ($i = 0; $i < 2; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map->getKey(),
            ]);
            $game->update(['winner_id' => $p1->getKey()]);
        }

        $response = $this->getJson("/players/{$p1->getKey()}/favorite-maps");

        $response->assertOk();
        // most_played works (no minimum), best_win_rate doesn't (needs 3)
        $this->assertNotNull($response->json('most_played'));
        $this->assertNull($response->json('best_win_rate'));
    }

    public function test_returns_all_maps_sorted(): void
    {
        $map1 = $this->setupMap();
        $map2 = $this->setupMap(attributes: ['name' => 'other-map']);
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // 2 games on map1
        for ($i = 0; $i < 2; $i++) {
            Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map1->getKey(),
            ]);
        }

        // 1 game on map2
        Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map2->getKey(),
        ]);

        $response = $this->getJson("/players/{$p1->getKey()}/favorite-maps");

        $response->assertOk();
        $allMaps = $response->json('all_maps');
        $this->assertCount(2, $allMaps);
        // Sorted by games_played descending
        $this->assertEquals(2, $allMaps[0]['games_played']);
        $this->assertEquals(1, $allMaps[1]['games_played']);
    }

    public function test_unknown_player_returns_404(): void
    {
        $response = $this->getJson('/players/nonexistent-uuid/favorite-maps');

        $response->assertNotFound();
    }
}
