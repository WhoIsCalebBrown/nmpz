<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\JsonResponse;

class PlayerComparisonController extends Controller
{
    public function __invoke(Player $player, Player $opponent): JsonResponse
    {
        $player->load('user', 'stats');
        $opponent->load('user', 'stats');

        return response()->json([
            'players' => [
                $this->formatPlayer($player),
                $this->formatPlayer($opponent),
            ],
        ]);
    }

    private function formatPlayer(Player $player): array
    {
        $stats = $player->stats;

        return [
            'player_id' => $player->getKey(),
            'name' => $player->user?->name ?? 'Unknown',
            'elo_rating' => $player->elo_rating,
            'rank' => $player->rank,
            'games_played' => $stats?->games_played ?? 0,
            'games_won' => $stats?->games_won ?? 0,
            'win_rate' => $stats?->win_rate ?? 0,
            'best_win_streak' => $stats?->best_win_streak ?? 0,
            'best_round_score' => $stats?->best_round_score ?? 0,
            'average_score' => $stats?->average_score ?? 0,
            'perfect_rounds' => $stats?->perfect_rounds ?? 0,
            'total_damage_dealt' => $stats?->total_damage_dealt ?? 0,
            'closest_guess_km' => $stats?->closest_guess_km ?? 0,
            'average_distance_km' => $stats?->average_distance_km ?? 0,
        ];
    }
}
