<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Map;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PlayerFavoriteMapsController extends Controller
{
    public function __invoke(Player $player): JsonResponse
    {
        $playerId = $player->getKey();

        $mapStats = DB::table('games')
            ->join('maps', 'maps.id', '=', 'games.map_id')
            ->where('games.status', GameStatus::Completed->value)
            ->where(fn ($q) => $q->where('player_one_id', $playerId)->orWhere('player_two_id', $playerId))
            ->groupBy('games.map_id', 'maps.display_name', 'maps.name')
            ->selectRaw(
                'games.map_id, COALESCE(maps.display_name, maps.name) as map_name, COUNT(*) as games_played, SUM(CASE WHEN games.winner_id = ? THEN 1 ELSE 0 END) as wins',
                [$playerId],
            )
            ->get()
            ->map(fn ($row) => [
                'map_id' => $row->map_id,
                'map_name' => $row->map_name,
                'games_played' => (int) $row->games_played,
                'wins' => (int) $row->wins,
                'win_rate' => $row->games_played > 0 ? round($row->wins / $row->games_played * 100, 1) : 0,
            ]);

        // Most played map
        $mostPlayed = $mapStats->sortByDesc('games_played')->first();

        // Best win rate map (min 3 games)
        $bestWinRate = $mapStats
            ->filter(fn ($m) => $m['games_played'] >= 3)
            ->sortByDesc('win_rate')
            ->first();

        return response()->json([
            'player_id' => $playerId,
            'most_played' => $mostPlayed,
            'best_win_rate' => $bestWinRate,
            'all_maps' => $mapStats->sortByDesc('games_played')->values()->all(),
        ]);
    }
}
