<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\PlayerStats;
use Illuminate\Http\JsonResponse;

class PlayerRankingController extends Controller
{
    public function __invoke(Player $player): JsonResponse
    {
        $playerElo = $player->elo_rating;

        // Get player's global rank (1-indexed)
        $rank = Player::query()
            ->where('elo_rating', '>', $playerElo)
            ->count() + 1;

        $totalPlayers = Player::query()->count();

        // Get players around this player (2 above, 2 below)
        $above = Player::query()
            ->where('elo_rating', '>', $playerElo)
            ->with('user', 'stats')
            ->orderBy('elo_rating')
            ->limit(2)
            ->get()
            ->reverse()
            ->values();

        $below = Player::query()
            ->where('elo_rating', '<', $playerElo)
            ->with('user', 'stats')
            ->orderByDesc('elo_rating')
            ->limit(2)
            ->get();

        $neighbors = collect()
            ->merge($above)
            ->push($player->load('user', 'stats'))
            ->merge($below)
            ->values()
            ->map(function (Player $p) use ($player) {
                $stats = $p->stats;

                return [
                    'player_id' => $p->getKey(),
                    'name' => $p->user?->name ?? 'Unknown',
                    'elo_rating' => $p->elo_rating,
                    'rank' => $p->rank,
                    'games_played' => $stats?->games_played ?? 0,
                    'win_rate' => $stats?->win_rate ?? 0,
                    'is_self' => $p->getKey() === $player->getKey(),
                ];
            });

        return response()->json([
            'player_id' => $player->getKey(),
            'global_rank' => $rank,
            'total_players' => $totalPlayers,
            'percentile' => $totalPlayers > 0 ? round((1 - ($rank - 1) / $totalPlayers) * 100, 1) : 0,
            'neighbors' => $neighbors,
        ]);
    }
}
