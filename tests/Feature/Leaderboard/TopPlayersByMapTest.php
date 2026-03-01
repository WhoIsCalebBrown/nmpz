<?php

namespace Tests\Feature\Leaderboard;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopPlayersByMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_top_players_for_each_map(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // p1 wins 3 games on this map
        for ($i = 0; $i < 3; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map->getKey(),
            ]);
            $game->update(['winner_id' => $p1->getKey()]);
        }

        $response = $this->getJson('/maps/top-players');

        $response->assertOk();
        $response->assertJsonStructure([
            'maps' => [[
                'map_id',
                'map_name',
                'top_player',
            ]],
        ]);

        $maps = $response->json('maps');
        $this->assertNotEmpty($maps);

        $mapEntry = collect($maps)->firstWhere('map_id', $map->getKey());
        $this->assertNotNull($mapEntry);
        $this->assertEquals($p1->getKey(), $mapEntry['top_player']['player_id']);
        $this->assertEquals(3, $mapEntry['top_player']['wins']);
    }

    public function test_returns_null_top_player_for_map_without_games(): void
    {
        $map = $this->setupMap();

        $response = $this->getJson('/maps/top-players');

        $response->assertOk();
        $maps = $response->json('maps');
        $mapEntry = collect($maps)->firstWhere('map_id', $map->getKey());
        $this->assertNull($mapEntry['top_player']);
    }

    public function test_picks_player_with_most_wins(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // p1 wins 1 game
        $game1 = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $game1->update(['winner_id' => $p1->getKey()]);

        // p2 wins 2 games
        for ($i = 0; $i < 2; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map->getKey(),
            ]);
            $game->update(['winner_id' => $p2->getKey()]);
        }

        $response = $this->getJson('/maps/top-players');

        $response->assertOk();
        $maps = $response->json('maps');
        $mapEntry = collect($maps)->firstWhere('map_id', $map->getKey());
        $this->assertEquals($p2->getKey(), $mapEntry['top_player']['player_id']);
        $this->assertEquals(2, $mapEntry['top_player']['wins']);
    }

    public function test_returns_empty_when_no_maps(): void
    {
        // No maps seeded
        $response = $this->getJson('/maps/top-players');

        $response->assertOk();
        $this->assertEmpty($response->json('maps'));
    }
}
