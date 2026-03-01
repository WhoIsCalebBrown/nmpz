<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeadToHeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_head_to_head_stats(): void
    {
        $this->setupMap();

        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // Create 3 completed games: p1 wins 2, p2 wins 1
        $g1 = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
        ]);
        $g1->update(['winner_id' => $p1->getKey()]);

        $g2 = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
        ]);
        $g2->update(['winner_id' => $p1->getKey()]);

        $g3 = Game::factory()->completed()->create([
            'player_one_id' => $p2->getKey(),
            'player_two_id' => $p1->getKey(),
        ]);
        $g3->update(['winner_id' => $p2->getKey()]);

        $response = $this->getJson("/players/{$p1->getKey()}/head-to-head/{$p2->getKey()}");

        $response->assertOk();
        $response->assertJsonPath('total_games', 3);
        $response->assertJsonPath('player_wins', 2);
        $response->assertJsonPath('opponent_wins', 1);
        $response->assertJsonPath('draws', 0);
        $response->assertJsonStructure([
            'player' => ['player_id', 'name', 'elo_rating', 'rank'],
            'opponent' => ['player_id', 'name', 'elo_rating', 'rank'],
            'total_games',
            'player_wins',
            'opponent_wins',
            'draws',
            'total_rounds',
            'player_avg_score',
            'opponent_avg_score',
            'recent_games',
        ]);
    }

    public function test_returns_zero_stats_for_no_games(): void
    {
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        $response = $this->getJson("/players/{$p1->getKey()}/head-to-head/{$p2->getKey()}");

        $response->assertOk();
        $response->assertJsonPath('total_games', 0);
        $response->assertJsonPath('player_wins', 0);
        $response->assertJsonPath('opponent_wins', 0);
        $response->assertJsonPath('draws', 0);
        $response->assertJsonPath('player_avg_score', 0);
    }

    public function test_recent_games_limited_to_five(): void
    {
        $this->setupMap();

        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        for ($i = 0; $i < 8; $i++) {
            Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'winner_id' => $i % 2 === 0 ? $p1->getKey() : $p2->getKey(),
            ]);
        }

        $response = $this->getJson("/players/{$p1->getKey()}/head-to-head/{$p2->getKey()}");

        $response->assertOk();
        $response->assertJsonPath('total_games', 8);
        $this->assertCount(5, $response->json('recent_games'));
    }

    public function test_excludes_in_progress_games(): void
    {
        $this->setupMap();

        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        Game::factory()->inProgress()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
        ]);

        $response = $this->getJson("/players/{$p1->getKey()}/head-to-head/{$p2->getKey()}");

        $response->assertOk();
        $response->assertJsonPath('total_games', 0);
    }

    public function test_invalid_player_returns_404(): void
    {
        $p1 = Player::factory()->create();

        $response = $this->getJson("/players/{$p1->getKey()}/head-to-head/nonexistent-uuid");

        $response->assertNotFound();
    }
}
