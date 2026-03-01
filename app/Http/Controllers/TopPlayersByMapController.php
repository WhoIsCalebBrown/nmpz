<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Map;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TopPlayersByMapController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $data = Cache::remember('top_players_by_map', 600, function () {
            $maps = Map::active()->get();

            return $maps->map(function (Map $map) {
                $mapId = $map->getKey();

                // Get top winner on this map
                $topWinner = DB::table('games')
                    ->where('map_id', $mapId)
                    ->where('status', GameStatus::Completed)
                    ->whereNotNull('winner_id')
                    ->select('winner_id', DB::raw('COUNT(*) as wins'))
                    ->groupBy('winner_id')
                    ->orderByDesc('wins')
                    ->first();

                $topPlayer = null;
                if ($topWinner) {
                    $player = Player::with('user')->find($topWinner->winner_id);
                    if ($player) {
                        $totalGames = Game::query()
                            ->where('map_id', $mapId)
                            ->where('status', GameStatus::Completed)
                            ->where(fn ($q) => $q->where('player_one_id', $player->getKey())->orWhere('player_two_id', $player->getKey()))
                            ->count();

                        $topPlayer = [
                            'player_id' => $player->getKey(),
                            'name' => $player->user?->name ?? 'Unknown',
                            'wins' => (int) $topWinner->wins,
                            'games_played' => $totalGames,
                            'elo_rating' => $player->elo_rating,
                        ];
                    }
                }

                return [
                    'map_id' => $mapId,
                    'map_name' => $map->display_name ?? $map->name,
                    'top_player' => $topPlayer,
                ];
            })->values()->all();
        });

        return response()->json(['maps' => $data]);
    }
}
