<?php

namespace Tests\Feature;

use App\Models\DailyChallenge;
use App\Models\DailyChallengeEntry;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyChallengeHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_past_challenges(): void
    {
        $map = $this->setupMap();
        $player = Player::factory()->create();

        $challenge = DailyChallenge::factory()->create([
            'map_id' => $map->getKey(),
            'challenge_date' => now()->subDay(),
        ]);

        DailyChallengeEntry::factory()->completed(18000)->create([
            'daily_challenge_id' => $challenge->getKey(),
            'player_id' => $player->getKey(),
        ]);

        $response = $this->getJson('/daily-challenge/history');

        $response->assertOk();
        $response->assertJsonStructure([
            'challenges' => [[
                'id',
                'date',
                'map_name',
                'participants',
                'top_player',
            ]],
        ]);

        $challenges = $response->json('challenges');
        $this->assertCount(1, $challenges);
        $this->assertEquals($challenge->getKey(), $challenges[0]['id']);
        $this->assertEquals(1, $challenges[0]['participants']);
    }

    public function test_excludes_todays_challenge(): void
    {
        $map = $this->setupMap();

        DailyChallenge::factory()->create([
            'map_id' => $map->getKey(),
            'challenge_date' => today(),
        ]);

        $response = $this->getJson('/daily-challenge/history');

        $response->assertOk();
        $this->assertEmpty($response->json('challenges'));
    }

    public function test_shows_top_scorer(): void
    {
        $map = $this->setupMap();
        $p1 = Player::factory()->create();
        $p2 = Player::factory()->create();

        $challenge = DailyChallenge::factory()->create([
            'map_id' => $map->getKey(),
            'challenge_date' => now()->subDay(),
        ]);

        DailyChallengeEntry::factory()->completed(15000)->create([
            'daily_challenge_id' => $challenge->getKey(),
            'player_id' => $p1->getKey(),
        ]);

        DailyChallengeEntry::factory()->completed(20000)->create([
            'daily_challenge_id' => $challenge->getKey(),
            'player_id' => $p2->getKey(),
        ]);

        $response = $this->getJson('/daily-challenge/history');

        $response->assertOk();
        $challenges = $response->json('challenges');
        $this->assertEquals($p2->getKey(), $challenges[0]['top_player']['player_id']);
        $this->assertEquals(20000, $challenges[0]['top_player']['score']);
    }

    public function test_ordered_by_most_recent(): void
    {
        $map = $this->setupMap();

        $older = DailyChallenge::factory()->create([
            'map_id' => $map->getKey(),
            'challenge_date' => now()->subDays(3),
        ]);

        $newer = DailyChallenge::factory()->create([
            'map_id' => $map->getKey(),
            'challenge_date' => now()->subDay(),
        ]);

        $response = $this->getJson('/daily-challenge/history');

        $response->assertOk();
        $challenges = $response->json('challenges');
        $this->assertCount(2, $challenges);
        $this->assertEquals($newer->getKey(), $challenges[0]['id']);
    }

    public function test_returns_empty_when_no_history(): void
    {
        $response = $this->getJson('/daily-challenge/history');

        $response->assertOk();
        $this->assertEmpty($response->json('challenges'));
    }
}
