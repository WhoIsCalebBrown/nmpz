<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\JsonResponse;

class HeadToHeadMapsController extends Controller
{
    public function __invoke(Player $player, Player $opponent): JsonResponse
    {
        $playerId = $player->getKey();
        $opponentId = $opponent->getKey();

        $games = Game::query()
            ->where('status', GameStatus::Completed)
            ->where(function ($q) use ($playerId, $opponentId) {
                $q->where(function ($q2) use ($playerId, $opponentId) {
                    $q2->where('player_one_id', $playerId)->where('player_two_id', $opponentId);
                })->orWhere(function ($q2) use ($playerId, $opponentId) {
                    $q2->where('player_one_id', $opponentId)->where('player_two_id', $playerId);
                });
            })
            ->with('map')
            ->get(['id', 'map_id', 'winner_id', 'player_one_id', 'player_two_id']);

        $mapStats = [];

        foreach ($games as $game) {
            $mapId = $game->map_id;
            $mapName = $game->map?->display_name ?? $game->map?->name ?? 'Unknown';

            if (! isset($mapStats[$mapId])) {
                $mapStats[$mapId] = [
                    'map_id' => $mapId,
                    'map_name' => $mapName,
                    'total_games' => 0,
                    'player_wins' => 0,
                    'opponent_wins' => 0,
                    'draws' => 0,
                ];
            }

            $mapStats[$mapId]['total_games']++;

            if ($game->winner_id === $playerId) {
                $mapStats[$mapId]['player_wins']++;
            } elseif ($game->winner_id === $opponentId) {
                $mapStats[$mapId]['opponent_wins']++;
            } else {
                $mapStats[$mapId]['draws']++;
            }
        }

        $maps = collect($mapStats)->map(function ($ms) {
            $ms['player_win_rate'] = $ms['total_games'] > 0
                ? round($ms['player_wins'] / $ms['total_games'] * 100, 1)
                : 0;

            return $ms;
        })->sortByDesc('total_games')->values()->all();

        return response()->json([
            'player_id' => $playerId,
            'opponent_id' => $opponentId,
            'maps' => $maps,
        ]);
    }
}
