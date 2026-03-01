<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_returns_matching_players(): void
    {
        $user = User::factory()->create(['name' => 'GeoMaster42']);
        Player::factory()->for($user)->create();

        Player::factory()->create(); // random player, shouldn't match

        $response = $this->getJson('/players/search?q=GeoMaster');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.name', 'GeoMaster42');
        $response->assertJsonStructure([['player_id', 'name', 'elo_rating', 'rank']]);
    }

    public function test_search_is_case_insensitive(): void
    {
        $user = User::factory()->create(['name' => 'TestPlayer']);
        Player::factory()->for($user)->create();

        $response = $this->getJson('/players/search?q=testplayer');

        $response->assertOk();
        $response->assertJsonCount(1);
    }

    public function test_search_requires_minimum_two_chars(): void
    {
        $response = $this->getJson('/players/search?q=a');

        $response->assertStatus(422);
    }

    public function test_search_requires_query(): void
    {
        $response = $this->getJson('/players/search');

        $response->assertStatus(422);
    }

    public function test_search_limits_results_to_twenty(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $user = User::factory()->create(['name' => "Player{$i}"]);
            Player::factory()->for($user)->create();
        }

        $response = $this->getJson('/players/search?q=Player');

        $response->assertOk();
        $response->assertJsonCount(20);
    }

    public function test_search_returns_empty_for_no_matches(): void
    {
        Player::factory()->create();

        $response = $this->getJson('/players/search?q=NonexistentName');

        $response->assertOk();
        $response->assertJsonCount(0);
    }

    public function test_search_returns_elo_and_rank(): void
    {
        $user = User::factory()->create(['name' => 'RankedPlayer']);
        Player::factory()->for($user)->create(['elo_rating' => 1500]);

        $response = $this->getJson('/players/search?q=RankedPlayer');

        $response->assertOk();
        $response->assertJsonPath('0.elo_rating', 1500);
        $response->assertJsonPath('0.rank', 'Platinum');
    }
}
