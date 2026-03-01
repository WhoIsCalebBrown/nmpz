<?php

namespace Tests\Feature\Leaderboard;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecentWinnersTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_recent_winners(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        $game = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $game->update(['winner_id' => $p1->getKey()]);

        $response = $this->getJson('/games/recent-winners');

        $response->assertOk();
        $response->assertJsonStructure([
            'winners' => [[
                'game_id',
                'winner_name',
                'winner_id',
                'winner_elo',
                'loser_name',
                'loser_id',
                'match_format',
                'finished_at',
            ]],
        ]);

        $winners = $response->json('winners');
        $this->assertCount(1, $winners);
        $this->assertEquals($p1->getKey(), $winners[0]['winner_id']);
        $this->assertEquals($p2->getKey(), $winners[0]['loser_id']);
    }

    public function test_excludes_draws(): void
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

        $response = $this->getJson('/games/recent-winners');

        $response->assertOk();
        $this->assertEmpty($response->json('winners'));
    }

    public function test_ordered_by_most_recent(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        $older = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
            'updated_at' => now()->subHours(2),
        ]);
        $older->update(['winner_id' => $p1->getKey(), 'updated_at' => now()->subHours(2)]);

        $newer = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $newer->update(['winner_id' => $p2->getKey()]);

        $response = $this->getJson('/games/recent-winners');

        $response->assertOk();
        $winners = $response->json('winners');
        $this->assertCount(2, $winners);
        $this->assertEquals($p2->getKey(), $winners[0]['winner_id']);
    }

    public function test_limited_to_15(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        for ($i = 0; $i < 20; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map->getKey(),
            ]);
            $game->update(['winner_id' => $p1->getKey()]);
        }

        $response = $this->getJson('/games/recent-winners');

        $response->assertOk();
        $this->assertCount(15, $response->json('winners'));
    }

    public function test_returns_empty_when_no_games(): void
    {
        $response = $this->getJson('/games/recent-winners');

        $response->assertOk();
        $this->assertEmpty($response->json('winners'));
    }
}
