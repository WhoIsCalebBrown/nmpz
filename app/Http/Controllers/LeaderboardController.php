<?php

namespace App\Http\Controllers;

use App\CacheKeys;
use App\Models\PlayerStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LeaderboardController extends Controller
{
    private const DB_SORT_COLUMNS = ['games_won', 'best_win_streak'];
    private const COMPUTED_SORTS = ['win_rate', 'elo_rating', 'average_score'];

    public function __invoke(Request $request): JsonResponse
    {
        $rank = $request->query('rank');
        $sortBy = $request->query('sort', 'games_won');

        $allowedSorts = [...self::DB_SORT_COLUMNS, ...self::COMPUTED_SORTS];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'games_won';
        }

        $cacheKey = CacheKeys::LEADERBOARD_MAIN . ":{$sortBy}" . ($rank ? ":{$rank}" : '');

        $entries = Cache::remember($cacheKey, 300, function () use ($sortBy, $rank) {
            $query = PlayerStats::query()
                ->where('games_played', '>=', 3)
                ->with('player.user');

            if (in_array($sortBy, self::DB_SORT_COLUMNS, true)) {
                $query->orderByDesc($sortBy);
            }

            $results = $query->limit(100)->get();

            // Apply computed sorts in-memory
            if (in_array($sortBy, self::COMPUTED_SORTS, true)) {
                $results = $results->sortByDesc(function ($s) use ($sortBy) {
                    return match ($sortBy) {
                        'elo_rating' => $s->player?->elo_rating ?? 0,
                        'win_rate' => $s->win_rate,
                        'average_score' => $s->average_score,
                    };
                })->values();
            }

            if ($rank) {
                $results = $results->filter(fn ($s) => ($s->player?->rank ?? 'Bronze') === $rank)->values();
            }

            return $results->take(50)->map(fn ($s) => [
                'player_id' => $s->player_id,
                'player_name' => $s->player?->user?->name ?? $s->player?->name ?? 'Unknown',
                'games_won' => $s->games_won,
                'games_played' => $s->games_played,
                'win_rate' => $s->win_rate,
                'best_win_streak' => $s->best_win_streak,
                'average_score' => $s->average_score,
                'elo_rating' => $s->player?->elo_rating ?? 1000,
                'rank' => $s->player?->rank ?? 'Bronze',
            ])->values();
        });

        return response()->json($entries);
    }
}
