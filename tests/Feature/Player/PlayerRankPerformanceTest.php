<?php

namespace Tests\Feature\Player;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerRankPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_all_ranks_for_new_player(): void
    {
        $player = Player::factory()->create();

        $response = $this->getJson("/players/{$player->getKey()}/rank-performance");

        $response->assertOk();
        $response->assertJsonCount(6, 'rank_performance');

        $ranks = collect($response->json('rank_performance'))->pluck('rank')->all();
        $this->assertEquals(['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Master'], $ranks);

        // All should have 0 games
        foreach ($response->json('rank_performance') as $rank) {
            $this->assertEquals(0, $rank['games_played']);
        }
    }

    public function test_calculates_win_rate_per_rank(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->withElo(1200)->create(); // Gold rank
        $bronzeOpponent = Player::factory()->withElo(700)->create(); // Bronze
        $goldOpponent = Player::factory()->withElo(1200)->create(); // Gold

        // 2 wins vs Bronze
        for ($i = 0; $i < 2; $i++) {
            $game = Game::factory()->completed()->create([
                'player_one_id' => $player->getKey(),
                'player_two_id' => $bronzeOpponent->getKey(),
                'map_id' => $map->getKey(),
            ]);
            $game->update(['winner_id' => $player->getKey()]);
        }

        // 1 win, 1 loss vs Gold
        $game = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $goldOpponent->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $game->update(['winner_id' => $player->getKey()]);

        $game = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $goldOpponent->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $game->update(['winner_id' => $goldOpponent->getKey()]);

        $response = $this->getJson("/players/{$player->getKey()}/rank-performance");

        $response->assertOk();

        $ranked = collect($response->json('rank_performance'))->keyBy('rank');

        $this->assertEquals(2, $ranked['Bronze']['games_played']);
        $this->assertEquals(2, $ranked['Bronze']['wins']);
        $this->assertEquals(100, $ranked['Bronze']['win_rate']);

        $this->assertEquals(2, $ranked['Gold']['games_played']);
        $this->assertEquals(1, $ranked['Gold']['wins']);
        $this->assertEquals(1, $ranked['Gold']['losses']);
        $this->assertEquals(50, $ranked['Gold']['win_rate']);

        // Other ranks should have 0 games
        $this->assertEquals(0, $ranked['Silver']['games_played']);
        $this->assertEquals(0, $ranked['Diamond']['games_played']);
    }

    public function test_works_when_player_is_player_two(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->withElo(1000)->create();
        $opponent = Player::factory()->withElo(900)->create(); // Silver

        $game = Game::factory()->completed()->create([
            'player_one_id' => $opponent->getKey(),
            'player_two_id' => $player->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $game->update(['winner_id' => $player->getKey()]);

        $response = $this->getJson("/players/{$player->getKey()}/rank-performance");

        $response->assertOk();
        $ranked = collect($response->json('rank_performance'))->keyBy('rank');
        $this->assertEquals(1, $ranked['Silver']['games_played']);
        $this->assertEquals(1, $ranked['Silver']['wins']);
    }

    public function test_returns_404_for_invalid_player(): void
    {
        $response = $this->getJson('/players/nonexistent-uuid/rank-performance');

        $response->assertNotFound();
    }
}
