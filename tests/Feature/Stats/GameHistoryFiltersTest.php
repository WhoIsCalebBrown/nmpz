<?php

namespace Tests\Feature\Stats;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GameHistoryFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_by_opponent(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent1 = Player::factory()->create();
        $opponent2 = Player::factory()->create();

        $gameVsOp1 = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent1->getKey(),
            'map_id' => $map->getKey(),
        ]);

        Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent2->getKey(),
            'map_id' => $map->getKey(),
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/games?opponent={$opponent1->getKey()}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.game_id', $gameVsOp1->getKey());
    }

    public function test_filter_by_map(): void
    {
        $map1 = $this->setupMap(10, ['name' => 'map-one']);
        $map2 = $this->setupMap(10, ['name' => 'map-two']);
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        $gameOnMap1 = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map1->getKey(),
        ]);

        Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map2->getKey(),
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/games?map={$map1->getKey()}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.game_id', $gameOnMap1->getKey());
    }

    public function test_filter_by_format(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        $rushGame = Game::factory()->completed()->rush()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
        ]);

        Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
            'match_format' => 'classic',
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/games?format=rush");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.game_id', $rushGame->getKey());
    }

    public function test_filter_by_result_win(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        $wonGame = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $wonGame->update(['winner_id' => $player->getKey()]);

        $lostGame = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $lostGame->update(['winner_id' => $opponent->getKey()]);

        $response = $this->getJson("/players/{$player->getKey()}/games?result=win");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.game_id', $wonGame->getKey());
    }

    public function test_filter_by_result_loss(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        $wonGame = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $wonGame->update(['winner_id' => $player->getKey()]);

        $lostGame = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $lostGame->update(['winner_id' => $opponent->getKey()]);

        $response = $this->getJson("/players/{$player->getKey()}/games?result=loss");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.game_id', $lostGame->getKey());
    }

    public function test_filter_by_date_range(): void
    {
        Carbon::setTestNow(now());
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        $recentGame = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
            'created_at' => now()->subDays(3),
        ]);

        Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
            'created_at' => now()->subDays(30),
        ]);

        $from = now()->subDays(7)->toDateString();
        $response = $this->getJson("/players/{$player->getKey()}/games?from={$from}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.game_id', $recentGame->getKey());

        Carbon::setTestNow();
    }

    public function test_combine_multiple_filters(): void
    {
        Carbon::setTestNow(now());
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        // Recent win on this map
        $target = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
            'created_at' => now()->subDays(2),
        ]);
        $target->update(['winner_id' => $player->getKey()]);

        // Recent loss on this map
        $other = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
            'created_at' => now()->subDays(2),
        ]);
        $other->update(['winner_id' => $opponent->getKey()]);

        $from = now()->subDays(7)->toDateString();
        $response = $this->getJson("/players/{$player->getKey()}/games?result=win&map={$map->getKey()}&from={$from}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.game_id', $target->getKey());

        Carbon::setTestNow();
    }

    public function test_no_filter_returns_all_games(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            Game::factory()->completed()->create([
                'player_one_id' => $player->getKey(),
                'player_two_id' => $opponent->getKey(),
                'map_id' => $map->getKey(),
            ]);
        }

        $response = $this->getJson("/players/{$player->getKey()}/games");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }
}
