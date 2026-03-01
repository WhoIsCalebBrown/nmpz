<?php

namespace Tests\Feature\Player;

use App\Models\Game;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerGameLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_log_for_new_player(): void
    {
        $player = Player::factory()->create();

        $response = $this->getJson("/players/{$player->getKey()}/game-log");

        $response->assertOk();
        $response->assertJsonPath('games', []);
    }

    public function test_returns_game_log_with_round_details(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        $game = Game::factory()->completed()->create([
            'player_one_id' => $player->getKey(),
            'player_two_id' => $opponent->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $game->update(['winner_id' => $player->getKey()]);

        $location = $map->locations()->first();
        Round::create([
            'location_heading' => 0,
            'game_id' => $game->getKey(),
            'round_number' => 1,
            'location_lat' => $location->lat,
            'location_lng' => $location->lng,
            'player_one_score' => 5000,
            'player_two_score' => 3000,
            'player_one_guess_lat' => $location->lat,
            'player_one_guess_lng' => $location->lng,
            'player_two_guess_lat' => $location->lat + 1,
            'player_two_guess_lng' => $location->lng + 1,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/game-log");

        $response->assertOk();
        $response->assertJsonCount(1, 'games');
        $response->assertJsonPath('games.0.result', 'win');
        $response->assertJsonPath('games.0.game_id', $game->getKey());
        $response->assertJsonCount(1, 'games.0.rounds');
        $response->assertJsonPath('games.0.rounds.0.my_score', 5000);
        $response->assertJsonPath('games.0.rounds.0.opponent_score', 3000);
        $response->assertJsonPath('games.0.rounds.0.won_round', true);
        $response->assertJsonPath('games.0.rounds.0.perfect', true);
    }

    public function test_perspective_correct_when_player_two(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        $game = Game::factory()->completed()->create([
            'player_one_id' => $opponent->getKey(),
            'player_two_id' => $player->getKey(),
            'map_id' => $map->getKey(),
        ]);
        $game->update(['winner_id' => $player->getKey()]);

        $location = $map->locations()->first();
        Round::create([
            'location_heading' => 0,
            'game_id' => $game->getKey(),
            'round_number' => 1,
            'location_lat' => $location->lat,
            'location_lng' => $location->lng,
            'player_one_score' => 2000,
            'player_two_score' => 4500,
            'player_one_guess_lat' => $location->lat + 2,
            'player_one_guess_lng' => $location->lng + 2,
            'player_two_guess_lat' => $location->lat + 0.01,
            'player_two_guess_lng' => $location->lng + 0.01,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinute(),
        ]);

        $response = $this->getJson("/players/{$player->getKey()}/game-log");

        $response->assertOk();
        // Player is P2, so "my_score" should be player_two_score
        $response->assertJsonPath('games.0.rounds.0.my_score', 4500);
        $response->assertJsonPath('games.0.rounds.0.opponent_score', 2000);
        $response->assertJsonPath('games.0.rounds.0.won_round', true);
    }

    public function test_limits_to_10_games(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();

        for ($i = 0; $i < 15; $i++) {
            Game::factory()->completed()->create([
                'player_one_id' => $player->getKey(),
                'player_two_id' => $opponent->getKey(),
                'map_id' => $map->getKey(),
            ]);
        }

        $response = $this->getJson("/players/{$player->getKey()}/game-log");

        $response->assertOk();
        $response->assertJsonCount(10, 'games');
    }

    public function test_returns_404_for_invalid_player(): void
    {
        $response = $this->getJson('/players/nonexistent-uuid/game-log');

        $response->assertNotFound();
    }
}
