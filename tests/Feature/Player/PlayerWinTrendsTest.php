<?php

namespace Tests\Feature\Player;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlayerWinTrendsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_trends_for_new_player(): void
    {
        $player = Player::factory()->create();

        $response = $this->getJson("/players/{$player->getKey()}/win-trends");

        $response->assertOk();
        $response->assertJsonPath('overall.games_played', 0);
        $response->assertJsonPath('overall.win_rate', 0);
        $response->assertJsonPath('trends.7d.games_played', 0);
        $response->assertJsonPath('trends.14d.games_played', 0);
        $response->assertJsonPath('trends.30d.games_played', 0);
        $response->assertJsonPath('form', 'inactive');
    }

    public function test_calculates_win_rates_per_window(): void
    {
        Carbon::setTestNow(now());
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        // 2 wins in last 7 days
        for ($i = 0; $i < 2; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $player->getKey(),
                'player_two_id' => $opponent->getKey(),
                'map_id' => $map->getKey(),
                'created_at' => now()->subDays(3),
            ]);
            $game->update(['winner_id' => $player->getKey()]);
        }

        // 1 loss in last 7 days
        $game = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
            'created_at' => now()->subDays(2),
        ]);
        $game->update(['winner_id' => $opponent->getKey()]);

        // 1 win 20 days ago (in 30d but not 14d)
        $game = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
            'created_at' => now()->subDays(20),
        ]);
        $game->update(['winner_id' => $player->getKey()]);

        $response = $this->getJson("/players/{$player->getKey()}/win-trends");

        $response->assertOk();
        $response->assertJsonPath('trends.7d.games_played', 3);
        $response->assertJsonPath('trends.7d.wins', 2);
        $response->assertJsonPath('trends.7d.losses', 1);
        $this->assertEqualsWithDelta(66.7, $response->json('trends.7d.win_rate'), 0.1);

        $response->assertJsonPath('trends.14d.games_played', 3);
        $response->assertJsonPath('trends.30d.games_played', 4);
        $response->assertJsonPath('trends.30d.wins', 3);

        $response->assertJsonPath('overall.games_played', 4);
        $response->assertJsonPath('overall.wins', 3);

        Carbon::setTestNow();
    }

    public function test_form_indicator_hot(): void
    {
        Carbon::setTestNow(now());
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        // Recent: 5 wins, 0 losses (100% win rate)
        for ($i = 0; $i < 5; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $player->getKey(),
                'player_two_id' => $opponent->getKey(),
                'map_id' => $map->getKey(),
                'created_at' => now()->subDays(2),
            ]);
            $game->update(['winner_id' => $player->getKey()]);
        }

        // Old: 5 losses (overall 50% win rate)
        for ($i = 0; $i < 5; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $player->getKey(),
                'player_two_id' => $opponent->getKey(),
                'map_id' => $map->getKey(),
                'created_at' => now()->subDays(40),
            ]);
            $game->update(['winner_id' => $opponent->getKey()]);
        }

        $response = $this->getJson("/players/{$player->getKey()}/win-trends");

        $response->assertOk();
        $response->assertJsonPath('form', 'hot');

        Carbon::setTestNow();
    }

    public function test_form_indicator_cold(): void
    {
        Carbon::setTestNow(now());
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        // Recent: 0 wins, 5 losses (0% win rate)
        for ($i = 0; $i < 5; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $player->getKey(),
                'player_two_id' => $opponent->getKey(),
                'map_id' => $map->getKey(),
                'created_at' => now()->subDays(2),
            ]);
            $game->update(['winner_id' => $opponent->getKey()]);
        }

        // Old: 5 wins (overall 50% win rate)
        for ($i = 0; $i < 5; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $player->getKey(),
                'player_two_id' => $opponent->getKey(),
                'map_id' => $map->getKey(),
                'created_at' => now()->subDays(40),
            ]);
            $game->update(['winner_id' => $player->getKey()]);
        }

        $response = $this->getJson("/players/{$player->getKey()}/win-trends");

        $response->assertOk();
        $response->assertJsonPath('form', 'cold');

        Carbon::setTestNow();
    }

    public function test_returns_404_for_invalid_player(): void
    {
        $response = $this->getJson('/players/nonexistent-uuid/win-trends');

        $response->assertNotFound();
    }
}
