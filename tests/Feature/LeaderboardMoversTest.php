<?php

namespace Tests\Feature;

use App\Models\EloHistory;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardMoversTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_when_no_activity(): void
    {
        $response = $this->getJson('/leaderboard/movers');

        $response->assertOk();
        $response->assertJsonPath('climbers', []);
        $response->assertJsonPath('fallers', []);
    }

    public function test_identifies_climbers(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create(['elo_rating' => 1100]);
        $p2 = Player::factory()->create();

        // p1 gains elo in 2 games
        for ($i = 0; $i < 2; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map->getKey(),
            ]);
            EloHistory::factory()->create([
                'player_id' => $p1->getKey(),
                'game_id' => $game->getKey(),
                'rating_change' => 25,
                'created_at' => now()->subDays(1),
            ]);
        }

        $response = $this->getJson('/leaderboard/movers');

        $response->assertOk();
        $climbers = $response->json('climbers');
        $this->assertNotEmpty($climbers);
        $this->assertEquals($p1->getKey(), $climbers[0]['player_id']);
        $this->assertEquals(50, $climbers[0]['net_change']);
    }

    public function test_identifies_fallers(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // p1 loses elo in 2 games
        for ($i = 0; $i < 2; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map->getKey(),
            ]);
            EloHistory::factory()->create([
                'player_id' => $p1->getKey(),
                'game_id' => $game->getKey(),
                'rating_change' => -20,
                'created_at' => now()->subDays(2),
            ]);
        }

        $response = $this->getJson('/leaderboard/movers');

        $response->assertOk();
        $fallers = $response->json('fallers');
        $this->assertNotEmpty($fallers);
        $this->assertEquals($p1->getKey(), $fallers[0]['player_id']);
        $this->assertEquals(-40, $fallers[0]['net_change']);
    }

    public function test_excludes_old_activity(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // Games from 10 days ago (outside 7-day window)
        for ($i = 0; $i < 2; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map->getKey(),
            ]);
            EloHistory::factory()->create([
                'player_id' => $p1->getKey(),
                'game_id' => $game->getKey(),
                'rating_change' => 30,
                'created_at' => now()->subDays(10),
            ]);
        }

        $response = $this->getJson('/leaderboard/movers');

        $response->assertOk();
        $this->assertEmpty($response->json('climbers'));
    }

    public function test_requires_minimum_two_games(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // Only 1 game
        $game = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);
        EloHistory::factory()->create([
            'player_id' => $p1->getKey(),
            'game_id' => $game->getKey(),
            'rating_change' => 50,
            'created_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/leaderboard/movers');

        $response->assertOk();
        $this->assertEmpty($response->json('climbers'));
    }
}
