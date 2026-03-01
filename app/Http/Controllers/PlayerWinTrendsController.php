<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\JsonResponse;

class PlayerWinTrendsController extends Controller
{
    public function __invoke(Player $player): JsonResponse
    {
        $playerId = $player->getKey();

        $windows = [
            '7d' => 7,
            '14d' => 14,
            '30d' => 30,
        ];

        $trends = [];

        foreach ($windows as $label => $days) {
            $games = Game::query()
                ->where('status', GameStatus::Completed)
                ->where('created_at', '>=', now()->subDays($days))
                ->where(fn ($q) => $q->where('player_one_id', $playerId)->orWhere('player_two_id', $playerId))
                ->get(['id', 'winner_id', 'created_at']);

            $wins = $games->where('winner_id', $playerId)->count();
            $total = $games->count();

            $trends[$label] = [
                'games_played' => $total,
                'wins' => $wins,
                'losses' => $total - $wins,
                'win_rate' => $total > 0 ? round($wins / $total * 100, 1) : 0,
            ];
        }

        // Overall stats for comparison
        $allGames = Game::query()
            ->where('status', GameStatus::Completed)
            ->where(fn ($q) => $q->where('player_one_id', $playerId)->orWhere('player_two_id', $playerId))
            ->get(['id', 'winner_id']);

        $totalWins = $allGames->where('winner_id', $playerId)->count();
        $totalGames = $allGames->count();
        $overallWinRate = $totalGames > 0 ? round($totalWins / $totalGames * 100, 1) : 0;

        // Determine form (comparing 7d to overall)
        $recentWinRate = $trends['7d']['win_rate'];
        $form = match (true) {
            $trends['7d']['games_played'] === 0 => 'inactive',
            $recentWinRate >= $overallWinRate + 10 => 'hot',
            $recentWinRate <= $overallWinRate - 10 => 'cold',
            default => 'stable',
        };

        return response()->json([
            'player_id' => $playerId,
            'overall' => [
                'games_played' => $totalGames,
                'wins' => $totalWins,
                'win_rate' => $overallWinRate,
            ],
            'trends' => $trends,
            'form' => $form,
        ]);
    }
}
