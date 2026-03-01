<?php

namespace App\Http\Controllers;

use App\CacheKeys;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class QueueStatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $queue = Cache::get(CacheKeys::MATCHMAKING_QUEUE, []);
        $queueMaps = Cache::get(CacheKeys::MATCHMAKING_QUEUE_MAPS, []);
        $queueFormats = Cache::get(CacheKeys::MATCHMAKING_QUEUE_FORMATS, []);

        $playerCount = count($queue);

        // Get elo distribution of queued players
        $eloRanges = [
            'bronze' => 0,   // < 800
            'silver' => 0,   // 800-1099
            'gold' => 0,     // 1100-1399
            'platinum' => 0,  // 1400-1699
            'diamond' => 0,  // 1700-1999
            'master' => 0,   // 2000+
        ];

        if (! empty($queue)) {
            $players = Player::whereIn('id', $queue)->get(['id', 'elo_rating']);

            foreach ($players as $player) {
                $rank = strtolower($player->rank);
                if (isset($eloRanges[$rank])) {
                    $eloRanges[$rank]++;
                }
            }
        }

        // Map preferences
        $mapPreferences = collect($queueMaps)
            ->countBy()
            ->sortDesc()
            ->all();

        // Format preferences
        $formatPreferences = collect($queueFormats)
            ->countBy()
            ->sortDesc()
            ->all();

        return response()->json([
            'player_count' => $playerCount,
            'elo_distribution' => $eloRanges,
            'map_preferences' => $mapPreferences,
            'format_preferences' => $formatPreferences,
        ]);
    }
}
