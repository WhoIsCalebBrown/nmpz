<?php

namespace Tests\Feature\Player;

use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerRankingTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_ranking_for_player(): void
    {
        $p1 = Player::factory()->create(['elo_rating' => 1200]);
        $p2 = Player::factory()->create(['elo_rating' => 1400]);
        $p3 = Player::factory()->create(['elo_rating' => 1000]);

        $response = $this->getJson("/players/{$p1->getKey()}/ranking");

        $response->assertOk();
        $response->assertJsonStructure([
            'player_id',
            'global_rank',
            'total_players',
            'percentile',
            'neighbors',
        ]);

        // p2 (1400) > p1 (1200) > p3 (1000), so p1 is rank 2
        $response->assertJsonPath('global_rank', 2);
        $response->assertJsonPath('total_players', 3);
    }

    public function test_shows_neighbors_around_player(): void
    {
        $players = [];
        foreach ([800, 900, 1000, 1100, 1200] as $elo) {
            $players[] = Player::factory()->create(['elo_rating' => $elo]);
        }

        // Middle player (1000 elo, index 2)
        $target = $players[2];
        $response = $this->getJson("/players/{$target->getKey()}/ranking");

        $response->assertOk();
        $neighbors = $response->json('neighbors');

        // Should have 5 entries (2 above, self, 2 below)
        $this->assertCount(5, $neighbors);

        // Self should be marked
        $selfEntry = collect($neighbors)->firstWhere('is_self', true);
        $this->assertNotNull($selfEntry);
        $this->assertEquals($target->getKey(), $selfEntry['player_id']);
    }

    public function test_top_ranked_player_has_fewer_above(): void
    {
        $p1 = Player::factory()->create(['elo_rating' => 1500]);
        $p2 = Player::factory()->create(['elo_rating' => 1200]);
        $p3 = Player::factory()->create(['elo_rating' => 1000]);

        $response = $this->getJson("/players/{$p1->getKey()}/ranking");

        $response->assertOk();
        $response->assertJsonPath('global_rank', 1);

        $neighbors = $response->json('neighbors');
        // Should have self + 2 below
        $this->assertCount(3, $neighbors);
        $this->assertTrue($neighbors[0]['is_self']);
    }

    public function test_calculates_percentile(): void
    {
        // Create 10 players
        for ($i = 0; $i < 9; $i++) {
            Player::factory()->create(['elo_rating' => 800 + ($i * 50)]);
        }
        $top = Player::factory()->create(['elo_rating' => 1500]);

        $response = $this->getJson("/players/{$top->getKey()}/ranking");

        $response->assertOk();
        $response->assertJsonPath('global_rank', 1);
        $response->assertJsonPath('total_players', 10);
        $this->assertEquals(100, $response->json('percentile'));
    }

    public function test_single_player_is_rank_one(): void
    {
        $player = Player::factory()->create();

        $response = $this->getJson("/players/{$player->getKey()}/ranking");

        $response->assertOk();
        $response->assertJsonPath('global_rank', 1);
        $response->assertJsonPath('total_players', 1);
        $this->assertEquals(100, $response->json('percentile'));
    }

    public function test_unknown_player_returns_404(): void
    {
        $response = $this->getJson('/players/nonexistent-uuid/ranking');

        $response->assertNotFound();
    }
}
