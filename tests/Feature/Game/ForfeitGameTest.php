<?php

namespace Tests\Feature\Game;

use App\Enums\GameStatus;
use App\Events\GameFinished;
use App\Jobs\ForceEndRound;
use App\Models\Game;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ForfeitGameTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_one_can_forfeit_and_player_two_wins(): void
    {
        Event::fake();
        $p1 = Player::factory()->withElo(1000)->create();
        $p2 = Player::factory()->withElo(1000)->create();
        $game = Game::factory()->inProgress()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
        ]);

        $response = $this->postJson(route('games.forfeit', [$p1, $game]));

        $response->assertOk();
        $response->assertJson(['forfeited' => true]);

        $game->refresh();
        $this->assertEquals(GameStatus::Completed, $game->status);
        $this->assertEquals($p2->getKey(), $game->winner_id);

        Event::assertDispatched(GameFinished::class, fn (GameFinished $e) => $e->game->getKey() === $game->getKey());
    }

    public function test_player_two_can_forfeit_and_player_one_wins(): void
    {
        Event::fake();
        $p1 = Player::factory()->withElo(1000)->create();
        $p2 = Player::factory()->withElo(1000)->create();
        $game = Game::factory()->inProgress()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
        ]);

        $response = $this->postJson(route('games.forfeit', [$p2, $game]));

        $response->assertOk();
        $response->assertJson(['forfeited' => true]);

        $game->refresh();
        $this->assertEquals(GameStatus::Completed, $game->status);
        $this->assertEquals($p1->getKey(), $game->winner_id);
    }

    public function test_cannot_forfeit_completed_game(): void
    {
        $p1 = Player::factory()->create();
        $game = Game::factory()->create([
            'player_one_id' => $p1->getKey(),
            'status' => GameStatus::Completed,
            'winner_id' => $p1->getKey(),
        ]);

        $response = $this->postJson(route('games.forfeit', [$p1, $game]));

        $response->assertStatus(422);
    }

    public function test_cannot_forfeit_game_you_are_not_in(): void
    {
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();
        $outsider = Player::factory()->create();
        $game = Game::factory()->inProgress()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
        ]);

        $response = $this->postJson(route('games.forfeit', [$outsider, $game]));

        $response->assertForbidden();
    }

    public function test_forfeit_cancels_pending_force_end_round_jobs(): void
    {
        Event::fake();
        $p1 = Player::factory()->withElo(1000)->create();
        $p2 = Player::factory()->withElo(1000)->create();
        $game = Game::factory()->inProgress()->create([
            'player_one_id' => $p1->getKey(),
            'player_two_id' => $p2->getKey(),
        ]);
        $round = Round::factory()->for($game)->create([
            'round_number' => 1,
            'finished_at' => null,
        ]);

        // Dispatch a ForceEndRound job so there's one to cancel
        ForceEndRound::dispatch($round->getKey());

        $this->postJson(route('games.forfeit', [$p1, $game]))
            ->assertOk();

        // The job row for this round should be removed
        $this->assertDatabaseMissing('jobs', [
            'payload' => '%' . $round->getKey() . '%',
        ]);
    }
}
