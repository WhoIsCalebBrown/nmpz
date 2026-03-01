<?php

namespace Tests\Feature\Stats;

use App\Models\DailyChallenge;
use App\Models\Game;
use App\Models\Map;
use App\Models\Player;
use App\Models\Round;
use App\Models\SoloGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_global_stats(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        $game = Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
        ]);

        Round::create([
            'game_id' => $game->getKey(),
            'round_number' => 1,
            'location_lat' => 0,
            'location_lng' => 0,
            'location_heading' => 0,
            'player_one_score' => 4000,
            'player_two_score' => 3000,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $response = $this->getJson('/stats/dashboard');

        $response->assertOk();
        $response->assertJsonStructure([
            'total_games',
            'total_rounds',
            'total_players',
            'active_players_7d',
            'average_round_score',
            'solo_games_played',
            'daily_challenges_completed',
            'games_per_day',
            'rank_distribution',
            'popular_maps',
        ]);

        $response->assertJsonPath('total_games', 1);
        $response->assertJsonPath('total_rounds', 1);
        $response->assertJsonPath('total_players', 2);
    }

    public function test_counts_solo_games(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->create();

        SoloGame::factory()->completed()->create([
            'player_id' => $player->getKey(),
            'map_id' => $map->getKey(),
        ]);

        $response = $this->getJson('/stats/dashboard');

        $response->assertOk();
        $response->assertJsonPath('solo_games_played', 1);
    }

    public function test_counts_daily_challenges(): void
    {
        $map = $this->setupMap();

        DailyChallenge::factory()->create([
            'map_id' => $map->getKey(),
        ]);

        $response = $this->getJson('/stats/dashboard');

        $response->assertOk();
        $response->assertJsonPath('daily_challenges_completed', 1);
    }

    public function test_returns_popular_maps(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            Game::factory()->completed()->create([
                'player_one_id' => $p1->getKey(),
                'player_two_id' => $p2->getKey(),
                'map_id' => $map->getKey(),
            ]);
        }

        $response = $this->getJson('/stats/dashboard');

        $response->assertOk();
        $popularMaps = $response->json('popular_maps');
        $this->assertNotEmpty($popularMaps);
        $this->assertEquals(3, $popularMaps[0]['games']);
    }

    public function test_returns_empty_stats_when_no_data(): void
    {
        $response = $this->getJson('/stats/dashboard');

        $response->assertOk();
        $response->assertJsonPath('total_games', 0);
        $response->assertJsonPath('total_rounds', 0);
        $response->assertJsonPath('total_players', 0);
        $response->assertJsonPath('active_players_7d', 0);
        $response->assertJsonPath('average_round_score', 0);
        $response->assertJsonPath('solo_games_played', 0);
        $response->assertJsonPath('daily_challenges_completed', 0);
    }

    public function test_returns_rank_distribution(): void
    {
        // rank is computed from elo_rating: <800=Bronze, 800+=Silver, 1100+=Gold
        Player::factory()->count(3)->create(['elo_rating' => 700]);  // Bronze
        Player::factory()->count(2)->create(['elo_rating' => 900]);  // Silver
        Player::factory()->create(['elo_rating' => 1200]);           // Gold

        $response = $this->getJson('/stats/dashboard');

        $response->assertOk();
        $rankDist = $response->json('rank_distribution');
        $this->assertEquals(3, $rankDist['Bronze']);
        $this->assertEquals(2, $rankDist['Silver']);
        $this->assertEquals(1, $rankDist['Gold']);
    }

    public function test_returns_games_per_day(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        // Create games on two different days
        Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
            'created_at' => now()->subDays(1),
        ]);
        Game::factory()->completed()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
            'map_id' => $map->getKey(),
            'created_at' => now(),
        ]);

        $response = $this->getJson('/stats/dashboard');

        $response->assertOk();
        $gamesPerDay = $response->json('games_per_day');
        $this->assertCount(2, $gamesPerDay);
    }
}
