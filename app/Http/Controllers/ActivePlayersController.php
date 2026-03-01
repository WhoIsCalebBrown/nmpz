<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ActivePlayersController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $since = now()->subDays(7);

        // Get game counts and wins per player in the last 7 days
        $p1Stats = DB::table('games')
            ->where('status', GameStatus::Completed)
            ->where('created_at', '>=', $since)
            ->selectRaw('player_one_id as player_id, COUNT(*) as games, SUM(CASE WHEN winner_id = player_one_id THEN 1 ELSE 0 END) as wins')
            ->groupBy('player_one_id');

        $p2Stats = DB::table('games')
            ->where('status', GameStatus::Completed)
            ->where('created_at', '>=', $since)
            ->selectRaw('player_two_id as player_id, COUNT(*) as games, SUM(CASE WHEN winner_id = player_two_id THEN 1 ELSE 0 END) as wins')
            ->groupBy('player_two_id');

        // Union and aggregate
        $combined = DB::query()
            ->fromSub(
                $p1Stats->unionAll($p2Stats),
                'all_stats'
            )
            ->selectRaw('player_id, SUM(games) as total_games, SUM(wins) as total_wins')
            ->groupBy('player_id')
            ->orderByDesc('total_games')
            ->limit(20)
            ->get();

        $playerIds = $combined->pluck('player_id')->all();
        $players = Player::with('user')->whereIn('id', $playerIds)->get()->keyBy('id');

        $result = $combined->map(function ($row) use ($players) {
            $player = $players->get($row->player_id);
            $totalGames = (int) $row->total_games;
            $totalWins = (int) $row->total_wins;

            return [
                'player_id' => $row->player_id,
                'name' => $player?->user?->name ?? 'Unknown',
                'elo_rating' => $player?->elo_rating ?? 1000,
                'rank' => $player?->rank ?? 'Bronze',
                'games_played' => $totalGames,
                'wins' => $totalWins,
                'win_rate' => $totalGames > 0 ? round($totalWins / $totalGames * 100, 1) : 0,
            ];
        })->values()->all();

        return response()->json([
            'period' => '7d',
            'players' => $result,
        ]);
    }
}
