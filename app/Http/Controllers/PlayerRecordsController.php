<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PlayerRecordsController extends Controller
{
    public function __invoke(Player $player): JsonResponse
    {
        $playerId = $player->getKey();

        // Best single round score
        $bestRound = $this->bestRoundScore($playerId);

        // Biggest elo gain in a single game
        $biggestEloGain = DB::table('elo_history')
            ->where('player_id', $playerId)
            ->orderByDesc('rating_change')
            ->first();

        // Biggest elo loss in a single game
        $biggestEloLoss = DB::table('elo_history')
            ->where('player_id', $playerId)
            ->orderBy('rating_change')
            ->first();

        // Most rounds in a single game
        $longestGame = Game::query()
            ->where('status', GameStatus::Completed)
            ->where(fn ($q) => $q->where('player_one_id', $playerId)->orWhere('player_two_id', $playerId))
            ->withCount(['rounds' => fn ($q) => $q->whereNotNull('finished_at')])
            ->orderByDesc('rounds_count')
            ->first();

        // Total perfect rounds (5000 score)
        $perfectRounds = $this->countPerfectRounds($playerId);

        // Best comeback: most rounds lost before winning
        $stats = $player->stats;

        return response()->json([
            'player_id' => $playerId,
            'records' => [
                'best_round_score' => $bestRound ? [
                    'score' => $bestRound->score,
                    'game_id' => $bestRound->game_id,
                ] : null,
                'perfect_rounds' => $perfectRounds,
                'biggest_elo_gain' => $biggestEloGain ? [
                    'change' => $biggestEloGain->rating_change,
                    'game_id' => $biggestEloGain->game_id,
                    'rating_after' => $biggestEloGain->rating_after,
                ] : null,
                'biggest_elo_loss' => $biggestEloLoss && $biggestEloLoss->rating_change < 0 ? [
                    'change' => $biggestEloLoss->rating_change,
                    'game_id' => $biggestEloLoss->game_id,
                    'rating_after' => $biggestEloLoss->rating_after,
                ] : null,
                'longest_game_rounds' => $longestGame ? [
                    'rounds' => $longestGame->rounds_count,
                    'game_id' => $longestGame->getKey(),
                ] : null,
                'total_games' => $stats?->games_played ?? 0,
                'total_wins' => $stats?->games_won ?? 0,
                'best_win_streak' => $stats?->best_win_streak ?? 0,
                'closest_guess_km' => $stats?->closest_guess_km ?? 0,
                'total_damage_dealt' => $stats?->total_damage_dealt ?? 0,
            ],
        ]);
    }

    private function bestRoundScore(string $playerId): ?object
    {
        $asP1 = DB::table('rounds')
            ->join('games', 'games.id', '=', 'rounds.game_id')
            ->where('games.player_one_id', $playerId)
            ->whereNotNull('rounds.finished_at')
            ->selectRaw('rounds.player_one_score as score, rounds.game_id')
            ->orderByDesc('score')
            ->first();

        $asP2 = DB::table('rounds')
            ->join('games', 'games.id', '=', 'rounds.game_id')
            ->where('games.player_two_id', $playerId)
            ->whereNotNull('rounds.finished_at')
            ->selectRaw('rounds.player_two_score as score, rounds.game_id')
            ->orderByDesc('score')
            ->first();

        if (! $asP1 && ! $asP2) {
            return null;
        }

        if (! $asP1) {
            return $asP2;
        }

        if (! $asP2) {
            return $asP1;
        }

        return $asP1->score >= $asP2->score ? $asP1 : $asP2;
    }

    private function countPerfectRounds(string $playerId): int
    {
        $asP1 = DB::table('rounds')
            ->join('games', 'games.id', '=', 'rounds.game_id')
            ->where('games.player_one_id', $playerId)
            ->whereNotNull('rounds.finished_at')
            ->where('rounds.player_one_score', '>=', 5000)
            ->count();

        $asP2 = DB::table('rounds')
            ->join('games', 'games.id', '=', 'rounds.game_id')
            ->where('games.player_two_id', $playerId)
            ->whereNotNull('rounds.finished_at')
            ->where('rounds.player_two_score', '>=', 5000)
            ->count();

        return $asP1 + $asP2;
    }
}
