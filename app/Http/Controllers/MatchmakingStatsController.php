<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MatchmakingStatsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        // Get ELO gaps from elo_history: for each game, find both entries and compute the gap
        $gameRatings = DB::table('elo_history')
            ->join('games', 'games.id', '=', 'elo_history.game_id')
            ->where('games.status', GameStatus::Completed)
            ->select([
                'elo_history.game_id',
                'elo_history.player_id',
                'elo_history.rating_before',
                'elo_history.opponent_rating',
                'games.winner_id',
                'games.player_one_id',
                'games.player_two_id',
            ])
            ->get()
            ->groupBy('game_id');

        // Deduplicate: use one entry per game (player_one's perspective)
        $matchups = [];
        foreach ($gameRatings as $gameId => $entries) {
            $p1Entry = $entries->firstWhere('player_id', $entries->first()->player_one_id);
            if (! $p1Entry) {
                $p1Entry = $entries->first();
            }

            $matchups[] = (object) [
                'game_id' => $gameId,
                'p1_rating' => $p1Entry->rating_before,
                'p2_rating' => $p1Entry->opponent_rating,
                'winner_id' => $p1Entry->winner_id,
                'player_one_id' => $p1Entry->player_one_id,
                'player_two_id' => $p1Entry->player_two_id,
            ];
        }

        if (empty($matchups)) {
            return response()->json([
                'total_games' => 0,
                'average_elo_gap' => 0,
                'median_elo_gap' => 0,
                'upset_rate' => 0,
                'balance_score' => 0,
                'gap_distribution' => [],
            ]);
        }

        $eloGaps = collect($matchups)->map(fn ($m) => abs($m->p1_rating - $m->p2_rating));

        $avgGap = round($eloGaps->avg(), 1);

        $sorted = $eloGaps->sort()->values();
        $count = $sorted->count();
        $medianGap = $count % 2 === 0
            ? round(($sorted[$count / 2 - 1] + $sorted[$count / 2]) / 2, 1)
            : round($sorted[intdiv($count, 2)], 1);

        // Upset rate: lower-rated player wins
        $upsets = collect($matchups)->filter(function ($m) {
            if (! $m->winner_id) {
                return false;
            }

            $p1Higher = $m->p1_rating >= $m->p2_rating;

            return ($p1Higher && $m->winner_id === $m->player_two_id)
                || (! $p1Higher && $m->winner_id === $m->player_one_id);
        })->count();

        $gamesWithWinner = collect($matchups)->filter(fn ($m) => $m->winner_id !== null)->count();
        $upsetRate = $gamesWithWinner > 0 ? round($upsets / $gamesWithWinner * 100, 1) : 0;

        // Balance score: 100 = perfect (all games <50 gap), decreases as gaps widen
        $balanceScore = round(max(0, 100 - ($avgGap / 5)), 1);

        // Gap distribution
        $buckets = [
            '0-50' => 0,
            '51-100' => 0,
            '101-200' => 0,
            '201-500' => 0,
            '500+' => 0,
        ];

        foreach ($eloGaps as $gap) {
            match (true) {
                $gap <= 50 => $buckets['0-50']++,
                $gap <= 100 => $buckets['51-100']++,
                $gap <= 200 => $buckets['101-200']++,
                $gap <= 500 => $buckets['201-500']++,
                default => $buckets['500+']++,
            };
        }

        return response()->json([
            'total_games' => count($matchups),
            'average_elo_gap' => $avgGap,
            'median_elo_gap' => $medianGap,
            'upset_rate' => $upsetRate,
            'balance_score' => $balanceScore,
            'gap_distribution' => $buckets,
        ]);
    }
}
