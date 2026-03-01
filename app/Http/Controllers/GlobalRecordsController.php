<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\PlayerStats;
use App\Models\Round;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GlobalRecordsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'longest_win_streak' => $this->longestWinStreak(),
            'highest_elo' => $this->highestElo(),
            'most_perfect_rounds' => $this->mostPerfectRounds(),
            'most_games_played' => $this->mostGamesPlayed(),
            'closest_guess' => $this->closestGuess(),
            'highest_single_round_score' => $this->highestSingleRoundScore(),
        ]);
    }

    private function longestWinStreak(): ?array
    {
        $record = PlayerStats::query()
            ->where('best_win_streak', '>', 0)
            ->orderByDesc('best_win_streak')
            ->with('player.user')
            ->first();

        if (! $record) {
            return null;
        }

        return [
            'player_id' => $record->player_id,
            'player_name' => $record->player?->user?->name ?? 'Unknown',
            'value' => $record->best_win_streak,
        ];
    }

    private function highestElo(): ?array
    {
        $record = DB::table('elo_history')
            ->join('players', 'players.id', '=', 'elo_history.player_id')
            ->join('users', 'users.id', '=', 'players.user_id')
            ->orderByDesc('elo_history.rating_after')
            ->select([
                'elo_history.player_id',
                'users.name as player_name',
                'elo_history.rating_after as value',
            ])
            ->first();

        if (! $record) {
            return null;
        }

        return [
            'player_id' => $record->player_id,
            'player_name' => $record->player_name,
            'value' => (int) $record->value,
        ];
    }

    private function mostPerfectRounds(): ?array
    {
        $record = PlayerStats::query()
            ->where('perfect_rounds', '>', 0)
            ->orderByDesc('perfect_rounds')
            ->with('player.user')
            ->first();

        if (! $record) {
            return null;
        }

        return [
            'player_id' => $record->player_id,
            'player_name' => $record->player?->user?->name ?? 'Unknown',
            'value' => $record->perfect_rounds,
        ];
    }

    private function mostGamesPlayed(): ?array
    {
        $record = PlayerStats::query()
            ->where('games_played', '>', 0)
            ->orderByDesc('games_played')
            ->with('player.user')
            ->first();

        if (! $record) {
            return null;
        }

        return [
            'player_id' => $record->player_id,
            'player_name' => $record->player?->user?->name ?? 'Unknown',
            'value' => $record->games_played,
        ];
    }

    private function closestGuess(): ?array
    {
        $record = PlayerStats::query()
            ->where('closest_guess_km', '>', 0)
            ->orderBy('closest_guess_km')
            ->with('player.user')
            ->first();

        if (! $record) {
            return null;
        }

        return [
            'player_id' => $record->player_id,
            'player_name' => $record->player?->user?->name ?? 'Unknown',
            'value' => round($record->closest_guess_km, 3),
        ];
    }

    private function highestSingleRoundScore(): ?array
    {
        // Check player_one best
        $best1 = DB::table('rounds')
            ->join('games', 'games.id', '=', 'rounds.game_id')
            ->join('players', 'players.id', '=', 'games.player_one_id')
            ->join('users', 'users.id', '=', 'players.user_id')
            ->whereNotNull('rounds.finished_at')
            ->orderByDesc('rounds.player_one_score')
            ->select([
                'games.player_one_id as player_id',
                'users.name as player_name',
                'rounds.player_one_score as value',
            ])
            ->first();

        $best2 = DB::table('rounds')
            ->join('games', 'games.id', '=', 'rounds.game_id')
            ->join('players', 'players.id', '=', 'games.player_two_id')
            ->join('users', 'users.id', '=', 'players.user_id')
            ->whereNotNull('rounds.finished_at')
            ->orderByDesc('rounds.player_two_score')
            ->select([
                'games.player_two_id as player_id',
                'users.name as player_name',
                'rounds.player_two_score as value',
            ])
            ->first();

        if (! $best1 && ! $best2) {
            return null;
        }

        $best = (! $best2 || ($best1 && $best1->value >= $best2->value)) ? $best1 : $best2;

        return [
            'player_id' => $best->player_id,
            'player_name' => $best->player_name,
            'value' => (int) $best->value,
        ];
    }
}
