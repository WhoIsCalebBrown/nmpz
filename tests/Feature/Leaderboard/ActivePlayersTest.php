<?php

namespace Tests\Feature\Leaderboard;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ActivePlayersTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_when_no_recent_games(): void
    {
        $response = $this->getJson('/leaderboard/active');

        $response->assertOk();
        $response->assertJsonPath('players', []);
        $response->assertJsonPath('period', '7d');
    }

    public function test_returns_most_active_players(): void
    {
        Carbon::setTestNow(now());
        $map = $this->setupMap();
        $active = Player::factory()->create();
        $moderate = Player::factory()->create();
        $opponent = Player::factory()->create();

        // Active player: 5 games
        for ($i = 0; $i < 5; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $active->getKey(),
                'player_two_id' => $opponent->getKey(),
                'map_id' => $map->getKey(),
                'created_at' => now()->subDays(2),
            ]);
            $game->update(['winner_id' => $active->getKey()]);
        }

        // Moderate player: 2 games
        for ($i = 0; $i < 2; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $moderate->getKey(),
                'player_two_id' => $opponent->getKey(),
                'map_id' => $map->getKey(),
                'created_at' => now()->subDays(3),
            ]);
            $game->update(['winner_id' => $opponent->getKey()]);
        }

        $response = $this->getJson('/leaderboard/active');

        $response->assertOk();
        // Active player should be first (most games)
        // Note: opponent also has 7 games total (5 + 2), so they may appear first
        $players = collect($response->json('players'));
        $activeEntry = $players->firstWhere('player_id', $active->getKey());
        $this->assertNotNull($activeEntry);
        $this->assertEquals(5, $activeEntry['games_played']);
        $this->assertEquals(5, $activeEntry['wins']);
        $this->assertEquals(100, $activeEntry['win_rate']);

        Carbon::setTestNow();
    }

    public function test_excludes_games_older_than_7_days(): void
    {
        Carbon::setTestNow(now());
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        // Old game (10 days ago)
        Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
            'created_at' => now()->subDays(10),
        ]);

        $response = $this->getJson('/leaderboard/active');

        $response->assertOk();
        $response->assertJsonPath('players', []);

        Carbon::setTestNow();
    }

    public function test_limits_to_20_players(): void
    {
        Carbon::setTestNow(now());
        $map = $this->setupMap();
        $opponent = Player::factory()->create();

        for ($i = 0; $i < 25; $i++) {
            $player = Player::factory()->create();
            Game::factory()->completed()->create([
                'player_one_id' => $player->getKey(),
                'player_two_id' => $opponent->getKey(),
                'map_id' => $map->getKey(),
                'created_at' => now()->subDays(1),
            ]);
        }

        $response = $this->getJson('/leaderboard/active');

        $response->assertOk();
        $this->assertLessThanOrEqual(20, count($response->json('players')));

        Carbon::setTestNow();
    }
}
