<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\PlayerStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_compares_two_players(): void
    {
        $p1 = Player::factory()->create(['elo_rating' => 1200]);
        $p2 = Player::factory()->create(['elo_rating' => 1400]);

        PlayerStats::factory()->create([
            'player_id' => $p1->getKey(),
            'games_played' => 10,
            'games_won' => 6,
            'best_round_score' => 4500,
        ]);

        PlayerStats::factory()->create([
            'player_id' => $p2->getKey(),
            'games_played' => 15,
            'games_won' => 12,
            'best_round_score' => 5000,
        ]);

        $response = $this->getJson("/players/{$p1->getKey()}/compare/{$p2->getKey()}");

        $response->assertOk();
        $response->assertJsonStructure([
            'players' => [
                ['player_id', 'name', 'elo_rating', 'rank', 'games_played', 'games_won', 'win_rate', 'best_win_streak', 'best_round_score', 'average_score', 'perfect_rounds'],
                ['player_id', 'name', 'elo_rating', 'rank', 'games_played', 'games_won', 'win_rate', 'best_win_streak', 'best_round_score', 'average_score', 'perfect_rounds'],
            ],
        ]);

        $players = $response->json('players');
        $this->assertEquals($p1->getKey(), $players[0]['player_id']);
        $this->assertEquals($p2->getKey(), $players[1]['player_id']);
        $this->assertEquals(1200, $players[0]['elo_rating']);
        $this->assertEquals(1400, $players[1]['elo_rating']);
    }

    public function test_works_without_stats(): void
    {
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        $response = $this->getJson("/players/{$p1->getKey()}/compare/{$p2->getKey()}");

        $response->assertOk();
        $players = $response->json('players');
        $this->assertEquals(0, $players[0]['games_played']);
        $this->assertEquals(0, $players[1]['games_played']);
    }

    public function test_returns_computed_stats(): void
    {
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        PlayerStats::factory()->create([
            'player_id' => $p1->getKey(),
            'games_played' => 20,
            'games_won' => 15,
            'total_rounds' => 50,
            'total_score' => 200000,
        ]);

        PlayerStats::factory()->create([
            'player_id' => $p2->getKey(),
            'games_played' => 10,
            'games_won' => 3,
            'total_rounds' => 30,
            'total_score' => 90000,
        ]);

        $response = $this->getJson("/players/{$p1->getKey()}/compare/{$p2->getKey()}");

        $response->assertOk();
        $players = $response->json('players');

        // p1: 15/20 = 75%
        $this->assertEquals(75, $players[0]['win_rate']);
        // p1: 200000/50 = 4000
        $this->assertEquals(4000, $players[0]['average_score']);
        // p2: 3/10 = 30%
        $this->assertEquals(30, $players[1]['win_rate']);
        // p2: 90000/30 = 3000
        $this->assertEquals(3000, $players[1]['average_score']);
    }

    public function test_unknown_player_returns_404(): void
    {
        $p1 = Player::factory()->create();

        $response = $this->getJson("/players/{$p1->getKey()}/compare/nonexistent-uuid");

        $response->assertNotFound();
    }

    public function test_can_compare_player_with_self(): void
    {
        $player = Player::factory()->create();

        $response = $this->getJson("/players/{$player->getKey()}/compare/{$player->getKey()}");

        $response->assertOk();
        $players = $response->json('players');
        $this->assertEquals($player->getKey(), $players[0]['player_id']);
        $this->assertEquals($player->getKey(), $players[1]['player_id']);
    }
}
