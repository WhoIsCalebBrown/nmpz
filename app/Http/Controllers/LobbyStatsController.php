<?php

namespace App\Http\Controllers;

use App\CacheKeys;
use App\Enums\GameStatus;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class LobbyStatsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $queue = Cache::get(CacheKeys::MATCHMAKING_QUEUE, []);

        $activeGames = Game::query()
            ->where('status', GameStatus::InProgress)
            ->count();

        $spectatorGames = Game::query()
            ->where('status', GameStatus::InProgress)
            ->where('allow_spectators', true)
            ->count();

        $gamesCompletedToday = Game::query()
            ->where('status', GameStatus::Completed)
            ->where('updated_at', '>=', today())
            ->count();

        // Queue wait times
        $queueTimes = Cache::get(CacheKeys::MATCHMAKING_QUEUE_TIMES, []);
        $avgWaitSeconds = ! empty($queueTimes) ? (int) (array_sum($queueTimes) / count($queueTimes)) : 0;

        return response()->json([
            'queue_size' => count($queue),
            'active_games' => $activeGames,
            'spectatable_games' => $spectatorGames,
            'games_today' => $gamesCompletedToday,
            'avg_wait_seconds' => $avgWaitSeconds,
        ]);
    }
}
