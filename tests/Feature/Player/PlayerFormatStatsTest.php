<?php

namespace Tests\Feature\Player;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerFormatStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_for_new_player(): void
    {
        $player = Player::factory()->create();

        $response = $this->getJson("/players/{$player->getKey()}/format-stats");

        $response->assertOk();
        $response->assertJsonPath('player_id', $player->getKey());
        $response->assertJsonPath('format_stats', []);
    }

    public function test_returns_stats_per_format(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // 2 classic games, p1 wins both
        for ($i = 0; $i < 2; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map->getKey(),
                'match_format' => 'classic',
            ]);
            $game->update(['winner_id' => $p1->getKey()]);
        }

        // 1 bo3 game, p1 loses
        $game = Game::factory()->completed()->bestOfThree()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $game->update(['winner_id' => $p2->getKey()]);

        $response = $this->getJson("/players/{$p1->getKey()}/format-stats");

        $response->assertOk();
        $stats = $response->json('format_stats');
        $this->assertCount(2, $stats);

        $classic = collect($stats)->firstWhere('format', 'classic');
        $this->assertEquals(2, $classic['games_played']);
        $this->assertEquals(2, $classic['wins']);
        $this->assertEquals(100.0, $classic['win_rate']);

        $bo3 = collect($stats)->firstWhere('format', 'bo3');
        $this->assertEquals(1, $bo3['games_played']);
        $this->assertEquals(0, $bo3['wins']);
        $this->assertEquals(1, $bo3['losses']);
    }

    public function test_tracks_draws(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        $game = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $game->update(['winner_id' => null]);

        $response = $this->getJson("/players/{$p1->getKey()}/format-stats");

        $response->assertOk();
        $stats = $response->json('format_stats');
        $classic = collect($stats)->first();
        $this->assertEquals(1, $classic['draws']);
    }

    public function test_sorted_by_most_played(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // 3 classic games
        for ($i = 0; $i < 3; $i++) {
            Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map->getKey(),
                'match_format' => 'classic',
            ]);
        }

        // 1 bo3 game
        Game::factory()->completed()->bestOfThree()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);

        $response = $this->getJson("/players/{$p1->getKey()}/format-stats");

        $response->assertOk();
        $stats = $response->json('format_stats');
        $this->assertEquals('classic', $stats[0]['format']);
        $this->assertEquals(3, $stats[0]['games_played']);
    }

    public function test_unknown_player_returns_404(): void
    {
        $response = $this->getJson('/players/nonexistent-uuid/format-stats');

        $response->assertNotFound();
    }
}
