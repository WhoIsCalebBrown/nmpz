<?php

namespace App\Http\Controllers;

use App\Models\EloHistory;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LeaderboardMoversController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $data = Cache::remember('leaderboard_movers', 300, function () {
            // Get net elo change per player in last 7 days
            $movers = DB::table('elo_history')
                ->where('created_at', '>=', now()->subDays(7))
                ->select('player_id', DB::raw('SUM(rating_change) as net_change'), DB::raw('COUNT(*) as games_played'))
                ->groupBy('player_id')
                ->having('games_played', '>=', 2)
                ->orderByDesc('net_change')
                ->get();

            if ($movers->isEmpty()) {
                return ['climbers' => [], 'fallers' => []];
            }

            $allPlayerIds = $movers->pluck('player_id')->all();
            $players = Player::with('user')->whereIn('id', $allPlayerIds)->get()->keyBy('id');

            $formatted = $movers->map(function ($row) use ($players) {
                $player = $players->get($row->player_id);

                return [
                    'player_id' => $row->player_id,
                    'name' => $player?->user?->name ?? 'Unknown',
                    'elo_rating' => $player?->elo_rating ?? 1000,
                    'rank' => $player?->rank ?? 'Bronze',
                    'net_change' => (int) $row->net_change,
                    'games_played' => (int) $row->games_played,
                ];
            });

            $climbers = $formatted->filter(fn ($p) => $p['net_change'] > 0)
                ->sortByDesc('net_change')
                ->take(10)
                ->values()
                ->all();

            $fallers = $formatted->filter(fn ($p) => $p['net_change'] < 0)
                ->sortBy('net_change')
                ->take(10)
                ->values()
                ->all();

            return [
                'climbers' => $climbers,
                'fallers' => $fallers,
            ];
        });

        return response()->json($data);
    }
}
