<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;
use App\Models\Round;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PlayerSkillProfileController extends Controller
{
    public function __invoke(Player $player): JsonResponse
    {
        $playerId = $player->getKey();
        $stats = $player->stats;

        if (! $stats || $stats->games_played < 3) {
            return response()->json([
                'player_id' => $playerId,
                'skills' => null,
                'archetype' => null,
                'message' => 'Not enough games played (minimum 3)',
            ]);
        }

        // Accuracy score (0-100): based on average round score vs max possible
        $maxScore = config('game.max_health');
        $avgScore = $stats->total_rounds > 0 ? $stats->total_score / $stats->total_rounds : 0;
        $accuracy = round(min(100, ($avgScore / $maxScore) * 100), 1);

        // Consistency score (0-100): based on score variance (lower = more consistent)
        $consistency = $this->calculateConsistency($playerId);

        // Clutch score (0-100): win rate in close games (games decided by <1000 health)
        $clutch = $this->calculateClutch($playerId);

        // Win rate score (0-100)
        $winRate = $stats->games_played > 0
            ? round($stats->games_won / $stats->games_played * 100, 1)
            : 0;

        // Perfect round rate (0-100)
        $perfectRate = $stats->total_rounds > 0
            ? round($stats->perfect_rounds / $stats->total_rounds * 100, 1)
            : 0;

        // Overall composite (weighted average)
        $overall = round(
            ($accuracy * 0.3) + ($consistency * 0.2) + ($clutch * 0.2) + ($winRate * 0.2) + ($perfectRate * 0.1),
            1
        );

        $skills = [
            'accuracy' => $accuracy,
            'consistency' => $consistency,
            'clutch' => $clutch,
            'win_rate' => $winRate,
            'perfect_rate' => $perfectRate,
            'overall' => $overall,
        ];

        // Determine archetype based on strongest skill
        $archetype = $this->determineArchetype($skills);

        return response()->json([
            'player_id' => $playerId,
            'skills' => $skills,
            'archetype' => $archetype,
        ]);
    }

    private function calculateConsistency(string $playerId): float
    {
        // Get all round scores for this player
        $p1Scores = DB::table('rounds')
            ->join('games', 'games.id', '=', 'rounds.game_id')
            ->where('games.player_one_id', $playerId)
            ->whereNotNull('rounds.finished_at')
            ->whereNotNull('rounds.player_one_score')
            ->pluck('rounds.player_one_score');

        $p2Scores = DB::table('rounds')
            ->join('games', 'games.id', '=', 'rounds.game_id')
            ->where('games.player_two_id', $playerId)
            ->whereNotNull('rounds.finished_at')
            ->whereNotNull('rounds.player_two_score')
            ->pluck('rounds.player_two_score');

        $allScores = $p1Scores->concat($p2Scores);

        if ($allScores->count() < 2) {
            return 50.0;
        }

        $mean = $allScores->avg();
        $variance = $allScores->map(fn ($s) => pow($s - $mean, 2))->avg();
        $stdDev = sqrt($variance);

        // Convert to 0-100 scale: lower std dev = higher consistency
        // 0 std dev = 100, 2500 std dev = 0
        return round(max(0, min(100, 100 - ($stdDev / 25))), 1);
    }

    private function calculateClutch(string $playerId): float
    {
        // Close games: both players ended with health > 0, or winner won with < 2000 health remaining
        $closeGames = Game::query()
            ->where('status', GameStatus::Completed)
            ->whereNotNull('winner_id')
            ->where(fn ($q) => $q->where('player_one_id', $playerId)->orWhere('player_two_id', $playerId))
            ->where(function ($q) {
                $q->where(function ($q2) {
                    // Winner had less than 40% health remaining
                    $threshold = (int) (config('game.max_health') * 0.4);
                    $q2->where(function ($q3) use ($threshold) {
                        $q3->whereColumn('winner_id', 'player_one_id')
                            ->where('player_one_health', '<', $threshold);
                    })->orWhere(function ($q3) use ($threshold) {
                        $q3->whereColumn('winner_id', 'player_two_id')
                            ->where('player_two_health', '<', $threshold);
                    });
                });
            })
            ->get(['id', 'winner_id']);

        if ($closeGames->isEmpty()) {
            return 50.0;
        }

        $wins = $closeGames->where('winner_id', $playerId)->count();
        $total = $closeGames->count();

        return round(($wins / $total) * 100, 1);
    }

    private function determineArchetype(array $skills): string
    {
        // Find the highest non-overall skill
        $skillCandidates = [
            'accuracy' => $skills['accuracy'],
            'consistency' => $skills['consistency'],
            'clutch' => $skills['clutch'],
            'win_rate' => $skills['win_rate'],
            'perfect_rate' => $skills['perfect_rate'],
        ];

        $topSkill = array_keys($skillCandidates, max($skillCandidates))[0];

        return match ($topSkill) {
            'accuracy' => 'Sharpshooter',
            'consistency' => 'Rock',
            'clutch' => 'Clutch Artist',
            'win_rate' => 'Winner',
            'perfect_rate' => 'Perfectionist',
        };
    }
}
