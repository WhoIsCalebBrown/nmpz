<?php

namespace Tests\Feature\Player;

use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerStats;
use App\Models\Round;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerSkillProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_skills_for_new_player(): void
    {
        $player = Player::factory()->create();

        $response = $this->getJson("/players/{$player->getKey()}/skill-profile");

        $response->assertOk();
        $response->assertJsonPath('skills', null);
        $response->assertJsonPath('archetype', null);
    }

    public function test_returns_null_skills_with_fewer_than_3_games(): void
    {
        $player = Player::factory()->create();
        PlayerStats::create([
            'player_id' => $player->getKey(),
            'games_played' => 2,
            'games_won' => 1,
            'total_rounds' => 5,
            'total_score' => 20000,
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/skill-profile");

        $response->assertOk();
        $response->assertJsonPath('skills', null);
    }

    public function test_returns_skill_profile_for_experienced_player(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        PlayerStats::create([
            'player_id' => $player->getKey(),
            'games_played' => 10,
            'games_won' => 7,
            'total_rounds' => 30,
            'total_score' => 120000,
            'perfect_rounds' => 3,
        ]);

        // Create some rounds for consistency calc
        $game = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $location = $map->locations()->first();

        for ($i = 1; $i <= 3; $i++) {
            Round::create([
                'location_heading' => 0,
                'game_id' => $game->getKey(),
                'round_number' => $i,
                'location_lat' => $location->lat,
                'location_lng' => $location->lng,
                'player_one_score' => 4000,
                'player_two_score' => 3000,
                'player_one_guess_lat' => $location->lat + 0.1,
                'player_one_guess_lng' => $location->lng + 0.1,
                'player_two_guess_lat' => $location->lat + 1,
                'player_two_guess_lng' => $location->lng + 1,
                'started_at' => now()->subMinutes($i + 1),
                'finished_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->getJson("/players/{$player->getKey()}/skill-profile");

        $response->assertOk();
        $response->assertJsonStructure([
            'player_id',
            'skills' => [
                'accuracy',
                'consistency',
                'clutch',
                'win_rate',
                'perfect_rate',
                'overall',
            ],
            'archetype',
        ]);

        // Accuracy: 120000/30 = 4000 avg, 4000/5000 * 100 = 80
        $this->assertEquals(80, $response->json('skills.accuracy'));
        // Win rate: 7/10 * 100 = 70
        $this->assertEquals(70, $response->json('skills.win_rate'));
        // Perfect rate: 3/30 * 100 = 10
        $this->assertEquals(10, $response->json('skills.perfect_rate'));

        $this->assertNotNull($response->json('archetype'));
    }

    public function test_assigns_sharpshooter_archetype_for_high_accuracy(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        PlayerStats::create([
            'player_id' => $player->getKey(),
            'games_played' => 10,
            'games_won' => 3,
            'total_rounds' => 10,
            'total_score' => 48000,
            'perfect_rounds' => 1,
        ]);

        $game = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $location = $map->locations()->first();

        // Varied high scores (some perfect, some mediocre — accuracy still high)
        $scores = [4900, 4700, 2000];
        for ($i = 0; $i < 3; $i++) {
            Round::create([
                'location_heading' => 0,
                'game_id' => $game->getKey(),
                'round_number' => $i + 1,
                'location_lat' => $location->lat,
                'location_lng' => $location->lng,
                'player_one_score' => $scores[$i],
                'player_two_score' => 3000,
                'player_one_guess_lat' => $location->lat,
                'player_one_guess_lng' => $location->lng,
                'player_two_guess_lat' => $location->lat + 1,
                'player_two_guess_lng' => $location->lng + 1,
                'started_at' => now()->subMinutes($i + 2),
                'finished_at' => now()->subMinutes($i + 1),
            ]);
        }

        $response = $this->getJson("/players/{$player->getKey()}/skill-profile");

        $response->assertOk();
        // Accuracy: 48000/10 = 4800, 4800/5000 * 100 = 96
        $this->assertEquals(96, $response->json('skills.accuracy'));
        $response->assertJsonPath('archetype', 'Sharpshooter');
    }

    public function test_assigns_winner_archetype_for_high_win_rate(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        PlayerStats::create([
            'player_id' => $player->getKey(),
            'games_played' => 10,
            'games_won' => 10,
            'total_rounds' => 10,
            'total_score' => 30000,
            'perfect_rounds' => 0,
        ]);

        $game = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $location = $map->locations()->first();

        // Varied scores (less consistent but winning)
        Round::create([
            'location_heading' => 0,
            'game_id' => $game->getKey(),
            'round_number' => 1,
            'location_lat' => $location->lat,
            'location_lng' => $location->lng,
            'player_one_score' => 4500,
            'player_two_score' => 3000,
            'player_one_guess_lat' => $location->lat,
            'player_one_guess_lng' => $location->lng,
            'player_two_guess_lat' => $location->lat + 1,
            'player_two_guess_lng' => $location->lng + 1,
            'started_at' => now()->subMinutes(3),
            'finished_at' => now()->subMinutes(2),
        ]);

        Round::create([
            'location_heading' => 0,
            'game_id' => $game->getKey(),
            'round_number' => 2,
            'location_lat' => $location->lat,
            'location_lng' => $location->lng,
            'player_one_score' => 1500,
            'player_two_score' => 3000,
            'player_one_guess_lat' => $location->lat + 3,
            'player_one_guess_lng' => $location->lng + 3,
            'player_two_guess_lat' => $location->lat + 1,
            'player_two_guess_lng' => $location->lng + 1,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/skill-profile");

        $response->assertOk();
        // Win rate = 100, should be highest skill
        $this->assertEquals(100, $response->json('skills.win_rate'));
        $response->assertJsonPath('archetype', 'Winner');
    }

    public function test_returns_404_for_invalid_player(): void
    {
        $response = $this->getJson('/players/nonexistent-uuid/skill-profile');

        $response->assertNotFound();
    }
}
