<?php

namespace App\Http\Controllers;

use App\CacheKeys;
use App\Enums\GameStatus;
use App\Models\DailyChallenge;
use App\Models\Game;
use App\Models\Player;
use App\Models\Round;
use App\Models\SoloGame;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GlobalStatsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $data = Cache::remember(CacheKeys::GLOBAL_STATS_DASHBOARD, 300, function () {
            $totalGames = Game::query()->where('status', GameStatus::Completed)->count();
            $totalRounds = Round::query()->whereNotNull('finished_at')->count();
            $totalPlayers = Player::query()->count();

            // Use DB query instead of loading all games into memory
            $activePlayers = DB::table('games')
                ->where('status', GameStatus::Completed)
                ->where('updated_at', '>=', now()->subDays(7))
                ->selectRaw('COUNT(DISTINCT player_one_id) + COUNT(DISTINCT player_two_id) as total')
                ->value('total');

            $avgRoundScore = Round::query()
                ->whereNotNull('finished_at')
                ->selectRaw('AVG(COALESCE(player_one_score, 0) + COALESCE(player_two_score, 0)) / 2 as avg_score')
                ->value('avg_score');

            // Games per day (last 14 days)
            $gamesPerDay = Game::query()
                ->where('status', GameStatus::Completed)
                ->where('created_at', '>=', now()->subDays(14))
                ->selectRaw("DATE(created_at) as date, COUNT(*) as count")
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(fn ($row) => ['date' => $row->date, 'count' => (int) $row->count]);

            // Rank distribution (rank is a computed accessor based on elo_rating)
            $rankDistribution = Player::query()
                ->get(['elo_rating'])
                ->groupBy(fn ($p) => $p->rank)
                ->map(fn ($group) => $group->count())
                ->sortDesc()
                ->all();

            // Solo game stats
            $soloGamesPlayed = SoloGame::query()
                ->where('status', 'completed')
                ->count();

            // Daily challenge stats
            $dailyChallengesCompleted = DailyChallenge::query()->count();

            // Most popular maps (by game count)
            $popularMaps = Game::query()
                ->where('status', GameStatus::Completed)
                ->join('maps', 'maps.id', '=', 'games.map_id')
                ->selectRaw('COALESCE(maps.display_name, maps.name) as map_name, COUNT(*) as game_count')
                ->groupBy('map_name')
                ->orderByDesc('game_count')
                ->limit(5)
                ->get()
                ->map(fn ($row) => ['name' => $row->map_name, 'games' => (int) $row->game_count]);

            return [
                'total_games' => $totalGames,
                'total_rounds' => $totalRounds,
                'total_players' => $totalPlayers,
                'active_players_7d' => (int) $activePlayers,
                'average_round_score' => $avgRoundScore ? round((float) $avgRoundScore) : 0,
                'solo_games_played' => $soloGamesPlayed,
                'daily_challenges_completed' => $dailyChallengesCompleted,
                'games_per_day' => $gamesPerDay,
                'rank_distribution' => $rankDistribution,
                'popular_maps' => $popularMaps,
            ];
        });

        return response()->json($data);
    }
}
