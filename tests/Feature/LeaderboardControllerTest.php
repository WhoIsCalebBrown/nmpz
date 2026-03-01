<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\PlayerStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_array_when_no_players(): void
    {
        $this->getJson('/leaderboard')
            ->assertOk()
            ->assertJson([]);
    }

    public function test_returns_players_with_at_least_three_games(): void
    {
        $qualifiedPlayer = Player::factory()->withElo(1200)->create();
        PlayerStats::create([
            'player_id' => $qualifiedPlayer->getKey(),
            'games_played' => 5,
            'games_won' => 3,
            'games_lost' => 2,
        ]);

        $unqualifiedPlayer = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $unqualifiedPlayer->getKey(),
            'games_played' => 2,
            'games_won' => 1,
            'games_lost' => 1,
        ]);

        $response = $this->getJson('/leaderboard')
            ->assertOk()
            ->assertJsonCount(1);

        $response->assertJsonFragment([
            'player_id' => $qualifiedPlayer->getKey(),
            'games_won' => 3,
        ]);
    }

    public function test_orders_by_games_won_descending(): void
    {
        $topPlayer = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $topPlayer->getKey(),
            'games_played' => 10,
            'games_won' => 8,
            'games_lost' => 2,
        ]);

        $secondPlayer = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $secondPlayer->getKey(),
            'games_played' => 10,
            'games_won' => 5,
            'games_lost' => 5,
        ]);

        $response = $this->getJson('/leaderboard')
            ->assertOk()
            ->assertJsonCount(2);

        $data = $response->json();
        $this->assertSame($topPlayer->getKey(), $data[0]['player_id']);
        $this->assertSame($secondPlayer->getKey(), $data[1]['player_id']);
    }

    public function test_includes_expected_fields(): void
    {
        $player = Player::factory()->withElo(1500)->create();
        PlayerStats::create([
            'player_id' => $player->getKey(),
            'games_played' => 5,
            'games_won' => 3,
            'games_lost' => 2,
            'best_win_streak' => 2,
        ]);

        $response = $this->getJson('/leaderboard')
            ->assertOk();

        $entry = $response->json()[0];
        $this->assertArrayHasKey('player_id', $entry);
        $this->assertArrayHasKey('player_name', $entry);
        $this->assertArrayHasKey('games_won', $entry);
        $this->assertArrayHasKey('games_played', $entry);
        $this->assertArrayHasKey('win_rate', $entry);
        $this->assertArrayHasKey('best_win_streak', $entry);
        $this->assertArrayHasKey('elo_rating', $entry);
        $this->assertArrayHasKey('rank', $entry);
    }

    public function test_sort_by_win_rate(): void
    {
        $lowWinRate = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $lowWinRate->getKey(),
            'games_played' => 10,
            'games_won' => 2,
            'games_lost' => 8,
        ]);

        $highWinRate = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $highWinRate->getKey(),
            'games_played' => 3,
            'games_won' => 3,
            'games_lost' => 0,
        ]);

        $response = $this->getJson('/leaderboard?sort=win_rate')
            ->assertOk()
            ->assertJsonCount(2);

        $data = $response->json();
        $this->assertSame($highWinRate->getKey(), $data[0]['player_id']);
    }

    public function test_sort_by_elo_rating(): void
    {
        $lowElo = Player::factory()->withElo(800)->create();
        PlayerStats::create([
            'player_id' => $lowElo->getKey(),
            'games_played' => 10,
            'games_won' => 8,
            'games_lost' => 2,
        ]);

        $highElo = Player::factory()->withElo(2000)->create();
        PlayerStats::create([
            'player_id' => $highElo->getKey(),
            'games_played' => 5,
            'games_won' => 3,
            'games_lost' => 2,
        ]);

        $response = $this->getJson('/leaderboard?sort=elo_rating')
            ->assertOk()
            ->assertJsonCount(2);

        $data = $response->json();
        $this->assertSame($highElo->getKey(), $data[0]['player_id']);
    }

    public function test_filter_by_rank(): void
    {
        $goldPlayer = Player::factory()->withElo(1200)->create();
        PlayerStats::create([
            'player_id' => $goldPlayer->getKey(),
            'games_played' => 5,
            'games_won' => 3,
            'games_lost' => 2,
        ]);

        $masterPlayer = Player::factory()->withElo(2100)->create();
        PlayerStats::create([
            'player_id' => $masterPlayer->getKey(),
            'games_played' => 5,
            'games_won' => 4,
            'games_lost' => 1,
        ]);

        $response = $this->getJson('/leaderboard?rank=Gold')
            ->assertOk()
            ->assertJsonCount(1);

        $response->assertJsonPath('0.player_id', $goldPlayer->getKey());
    }

    public function test_invalid_sort_falls_back_to_games_won(): void
    {
        $player = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $player->getKey(),
            'games_played' => 5,
            'games_won' => 3,
            'games_lost' => 2,
        ]);

        $this->getJson('/leaderboard?sort=invalid_column')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_limits_to_50_entries(): void
    {
        for ($i = 0; $i < 55; $i++) {
            $player = Player::factory()->create();
            PlayerStats::create([
                'player_id' => $player->getKey(),
                'games_played' => 5,
                'games_won' => 3,
                'games_lost' => 2,
            ]);
        }

        $this->getJson('/leaderboard')
            ->assertOk()
            ->assertJsonCount(50);
    }
}
