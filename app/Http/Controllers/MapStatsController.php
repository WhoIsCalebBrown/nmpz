<?php

namespace App\Http\Controllers;

use App\CacheKeys;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Map;
use App\Models\Round;
use App\Models\SoloGame;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MapStatsController extends Controller
{
    public function __invoke(Map $map): JsonResponse
    {
        $cacheKey = "map_stats:{$map->getKey()}";

        $data = Cache::remember($cacheKey, 300, function () use ($map) {
            $mapId = $map->getKey();

            // Total multiplayer games on this map
            $totalGames = Game::completed()
                ->where('map_id', $mapId)
                ->count();

            // Unique multiplayer players
            $uniquePlayers = DB::table('games')
                ->where('map_id', $mapId)
                ->where('status', GameStatus::Completed)
                ->selectRaw('COUNT(DISTINCT player_one_id) + COUNT(DISTINCT player_two_id) as total')
                ->value('total');

            // Average round score on this map
            $avgScore = Round::query()
                ->whereNotNull('finished_at')
                ->whereHas('game', fn ($q) => $q->where('map_id', $mapId))
                ->selectRaw('AVG(COALESCE(player_one_score, 0) + COALESCE(player_two_score, 0)) / 2 as avg_score')
                ->value('avg_score');

            // Highest round score on this map (use CASE for SQLite compat)
            $bestP1 = Round::query()
                ->whereNotNull('finished_at')
                ->whereHas('game', fn ($q) => $q->where('map_id', $mapId))
                ->max('player_one_score') ?? 0;

            $bestP2 = Round::query()
                ->whereNotNull('finished_at')
                ->whereHas('game', fn ($q) => $q->where('map_id', $mapId))
                ->max('player_two_score') ?? 0;

            $bestRoundScore = max($bestP1, $bestP2);

            // Solo games on this map
            $soloGamesPlayed = SoloGame::query()
                ->where('map_id', $mapId)
                ->where('status', 'completed')
                ->count();

            // Games per day (last 14 days)
            $gamesPerDay = Game::completed()
                ->where('map_id', $mapId)
                ->where('created_at', '>=', now()->subDays(14))
                ->selectRaw("DATE(created_at) as date, COUNT(*) as count")
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(fn ($row) => ['date' => $row->date, 'count' => (int) $row->count]);

            return [
                'map' => [
                    'id' => $map->getKey(),
                    'name' => $map->display_name ?? $map->name,
                    'description' => $map->description,
                    'location_count' => $map->location_count,
                ],
                'total_games' => $totalGames,
                'unique_players' => (int) $uniquePlayers,
                'average_round_score' => $avgScore ? round((float) $avgScore) : 0,
                'best_round_score' => (int) ($bestRoundScore ?? 0),
                'solo_games_played' => $soloGamesPlayed,
                'games_per_day' => $gamesPerDay,
            ];
        });

        return response()->json($data);
    }
}
