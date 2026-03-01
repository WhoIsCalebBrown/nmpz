<?php

namespace App\Http\Controllers;

use App\CacheKeys;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PlayerActivityController extends Controller
{
    private const HEARTBEAT_TTL_SECONDS = 120;

    public function heartbeat(Player $player): JsonResponse
    {
        Cache::put(CacheKeys::playerOnline($player->getKey()), true, self::HEARTBEAT_TTL_SECONDS);

        return response()->json(['status' => 'ok']);
    }

    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'player_ids' => ['required', 'array', 'max:50'],
            'player_ids.*' => ['required', 'uuid'],
        ]);

        $statuses = [];

        // Batch check for active games
        $inGamePlayerIds = Game::query()
            ->where('status', GameStatus::InProgress)
            ->whereIn('player_one_id', $validated['player_ids'])
            ->orWhere(function ($q) use ($validated) {
                $q->where('status', GameStatus::InProgress)
                    ->whereIn('player_two_id', $validated['player_ids']);
            })
            ->get()
            ->flatMap(fn (Game $g) => [$g->player_one_id, $g->player_two_id])
            ->intersect($validated['player_ids'])
            ->unique()
            ->values()
            ->all();

        foreach ($validated['player_ids'] as $playerId) {
            if (in_array($playerId, $inGamePlayerIds, true)) {
                $statuses[$playerId] = 'in_game';
            } elseif (Cache::has(CacheKeys::playerOnline($playerId))) {
                $statuses[$playerId] = 'online';
            } else {
                $statuses[$playerId] = 'offline';
            }
        }

        return response()->json($statuses);
    }
}
