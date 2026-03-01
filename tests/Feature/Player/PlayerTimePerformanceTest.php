<?php

namespace Tests\Feature\Player;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlayerTimePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_for_new_player(): void
    {
        $player = Player::factory()->create();

        $response = $this->getJson("/players/{$player->getKey()}/time-performance");

        $response->assertOk();
        $response->assertJsonPath('time_slots', []);
        $response->assertJsonPath('best_time', null);
        $response->assertJsonPath('worst_time', null);
    }

    public function test_groups_games_by_time_slot(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 1, 14, 0, 0));
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        // Morning game (8am)
        $game = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
            'created_at' => Carbon::create(2026, 3, 1, 8, 0, 0),
        ]);
        $game->update(['winner_id' => $player->getKey()]);

        // Afternoon game (2pm)
        $game = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
            'created_at' => Carbon::create(2026, 3, 1, 14, 0, 0),
        ]);
        $game->update(['winner_id' => $opponent->getKey()]);

        // Evening game (9pm)
        $game = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
            'created_at' => Carbon::create(2026, 3, 1, 21, 0, 0),
        ]);
        $game->update(['winner_id' => $player->getKey()]);

        $response = $this->getJson("/players/{$player->getKey()}/time-performance");

        $response->assertOk();
        $response->assertJsonCount(4, 'time_slots');

        $slots = collect($response->json('time_slots'))->keyBy('slot');
        $this->assertEquals(1, $slots['morning']['games_played']);
        $this->assertEquals(1, $slots['morning']['wins']);
        $this->assertEquals(1, $slots['afternoon']['games_played']);
        $this->assertEquals(0, $slots['afternoon']['wins']);
        $this->assertEquals(1, $slots['evening']['games_played']);
        $this->assertEquals(1, $slots['evening']['wins']);
        $this->assertEquals(0, $slots['night']['games_played']);

        Carbon::setTestNow();
    }

    public function test_identifies_best_and_worst_times(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 1, 14, 0, 0));
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        // 2 morning wins
        for ($i = 0; $i < 2; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $player->getKey(),
                'player_two_id' => $opponent->getKey(),
                'map_id' => $map->getKey(),
                'created_at' => Carbon::create(2026, 3, 1, 9, $i, 0),
            ]);
            $game->update(['winner_id' => $player->getKey()]);
        }

        // 2 evening losses
        for ($i = 0; $i < 2; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $player->getKey(),
                'player_two_id' => $opponent->getKey(),
                'map_id' => $map->getKey(),
                'created_at' => Carbon::create(2026, 3, 1, 20, $i, 0),
            ]);
            $game->update(['winner_id' => $opponent->getKey()]);
        }

        $response = $this->getJson("/players/{$player->getKey()}/time-performance");

        $response->assertOk();
        $response->assertJsonPath('best_time', 'morning');
        $response->assertJsonPath('worst_time', 'evening');

        Carbon::setTestNow();
    }

    public function test_requires_minimum_games_for_best_worst(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 1, 14, 0, 0));
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        // Only 1 morning game (not enough for best/worst)
        $game = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
            'created_at' => Carbon::create(2026, 3, 1, 9, 0, 0),
        ]);
        $game->update(['winner_id' => $player->getKey()]);

        $response = $this->getJson("/players/{$player->getKey()}/time-performance");

        $response->assertOk();
        $response->assertJsonPath('best_time', null);
        $response->assertJsonPath('worst_time', null);

        Carbon::setTestNow();
    }

    public function test_returns_404_for_invalid_player(): void
    {
        $response = $this->getJson('/players/nonexistent-uuid/time-performance');

        $response->assertNotFound();
    }
}
