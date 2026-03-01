<?php

namespace Tests\Feature\Player;

use App\Models\Player;
use App\Models\PlayerStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerMilestonesTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_milestones_structure(): void
    {
        $player = Player::factory()->create();

        $response = $this->getJson("/players/{$player->getKey()}/milestones");

        $response->assertOk();
        $response->assertJsonStructure([
            'player_id',
            'name',
            'milestones',
            'total_completed_games',
        ]);
    }

    public function test_win_streak_milestone(): void
    {
        $player = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $player->getKey(),
            'games_played' => 5,
            'games_won' => 5,
            'current_win_streak' => 5,
            'best_win_streak' => 5,
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/milestones");

        $response->assertOk();
        $milestones = collect($response->json('milestones'));
        $streak = $milestones->firstWhere('type', 'win_streak');
        $this->assertNotNull($streak);
        $this->assertEquals(5, $streak['value']);
    }

    public function test_no_streak_milestone_under_three(): void
    {
        $player = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $player->getKey(),
            'games_played' => 2,
            'games_won' => 2,
            'current_win_streak' => 2,
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/milestones");

        $milestones = collect($response->json('milestones'));
        $this->assertNull($milestones->firstWhere('type', 'win_streak'));
    }

    public function test_games_played_milestones(): void
    {
        $player = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $player->getKey(),
            'games_played' => 55,
            'games_won' => 30,
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/milestones");

        $milestones = collect($response->json('milestones'));
        $gamesPlayed = $milestones->where('type', 'games_played');
        // Should have 10, 25, 50 milestones (55 >= all three)
        $this->assertCount(3, $gamesPlayed);
    }

    public function test_rank_milestone_for_non_bronze(): void
    {
        $player = Player::factory()->withElo(1500)->create();

        $response = $this->getJson("/players/{$player->getKey()}/milestones");

        $milestones = collect($response->json('milestones'));
        $rank = $milestones->firstWhere('type', 'rank');
        $this->assertNotNull($rank);
        $this->assertStringContainsString('Platinum', $rank['label']);
    }

    public function test_no_rank_milestone_for_bronze(): void
    {
        $player = Player::factory()->withElo(700)->create();

        $response = $this->getJson("/players/{$player->getKey()}/milestones");

        $milestones = collect($response->json('milestones'));
        $this->assertNull($milestones->firstWhere('type', 'rank'));
    }

    public function test_perfect_round_milestone(): void
    {
        $player = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $player->getKey(),
            'games_played' => 5,
            'perfect_rounds' => 3,
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/milestones");

        $milestones = collect($response->json('milestones'));
        $perfect = $milestones->firstWhere('type', 'perfect_rounds');
        $this->assertNotNull($perfect);
        $this->assertEquals(3, $perfect['value']);
    }

    public function test_accuracy_milestone(): void
    {
        $player = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $player->getKey(),
            'games_played' => 5,
            'closest_guess_km' => 0.15,
            'total_guesses_made' => 25,
            'total_distance_km' => 50.0,
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/milestones");

        $milestones = collect($response->json('milestones'));
        $accuracy = $milestones->firstWhere('type', 'accuracy');
        $this->assertNotNull($accuracy);
        $this->assertStringContainsString('0.15', $accuracy['label']);
    }

    public function test_elite_win_rate(): void
    {
        $player = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $player->getKey(),
            'games_played' => 20,
            'games_won' => 16,
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/milestones");

        $milestones = collect($response->json('milestones'));
        $winRate = $milestones->firstWhere('type', 'win_rate');
        $this->assertNotNull($winRate);
    }

    public function test_no_win_rate_milestone_under_10_games(): void
    {
        $player = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $player->getKey(),
            'games_played' => 5,
            'games_won' => 5,
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/milestones");

        $milestones = collect($response->json('milestones'));
        $this->assertNull($milestones->firstWhere('type', 'win_rate'));
    }
}
