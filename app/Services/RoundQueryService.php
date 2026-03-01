<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RoundQueryService
{
    public static function bestRoundScore(string $playerId): ?object
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

    public static function countPerfectRounds(string $playerId): int
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
