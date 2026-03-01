<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerStats;
use Illuminate\Http\JsonResponse;

class PlayerMilestonesController extends Controller
{
    public function __invoke(Player $player): JsonResponse
    {
        $player->load('user', 'stats');
        $stats = $player->stats;

        $milestones = [];

        if ($stats) {
            // Win streak milestones
            if ($stats->current_win_streak >= 3) {
                $milestones[] = [
                    'type' => 'win_streak',
                    'label' => "{$stats->current_win_streak} Win Streak",
                    'value' => $stats->current_win_streak,
                    'icon' => 'fire',
                ];
            }

            // Games played milestones
            foreach ([10, 25, 50, 100, 250, 500, 1000] as $threshold) {
                if ($stats->games_played >= $threshold) {
                    $milestones[] = [
                        'type' => 'games_played',
                        'label' => "{$threshold} Games Played",
                        'value' => $threshold,
                        'icon' => 'trophy',
                    ];
                }
            }

            // Win count milestones
            foreach ([5, 10, 25, 50, 100, 250] as $threshold) {
                if ($stats->games_won >= $threshold) {
                    $milestones[] = [
                        'type' => 'games_won',
                        'label' => "{$threshold} Victories",
                        'value' => $threshold,
                        'icon' => 'star',
                    ];
                }
            }

            // Perfect round milestones
            if ($stats->perfect_rounds >= 1) {
                $milestones[] = [
                    'type' => 'perfect_rounds',
                    'label' => "{$stats->perfect_rounds} Perfect Round" . ($stats->perfect_rounds > 1 ? 's' : ''),
                    'value' => $stats->perfect_rounds,
                    'icon' => 'bullseye',
                ];
            }

            // Win rate milestone (min 10 games)
            if ($stats->games_played >= 10 && $stats->win_rate >= 70) {
                $milestones[] = [
                    'type' => 'win_rate',
                    'label' => 'Elite Win Rate (' . round($stats->win_rate, 1) . '%)',
                    'value' => $stats->win_rate,
                    'icon' => 'chart',
                ];
            }

            // Accuracy milestone
            if ($stats->closest_guess_km !== null && $stats->closest_guess_km < 1.0) {
                $milestones[] = [
                    'type' => 'accuracy',
                    'label' => 'Sub-1km Guess (' . round($stats->closest_guess_km, 2) . ' km)',
                    'value' => $stats->closest_guess_km,
                    'icon' => 'target',
                ];
            }
        }

        // ELO rank milestones
        $elo = $player->elo_rating;
        $rank = $player->rank;
        if ($rank !== 'Bronze') {
            $milestones[] = [
                'type' => 'rank',
                'label' => "{$rank} Rank ({$elo} ELO)",
                'value' => $elo,
                'icon' => 'shield',
            ];
        }

        // Total games as player (to know if active)
        $totalGames = Game::query()
            ->where('status', GameStatus::Completed)
            ->where(fn ($q) => $q->where('player_one_id', $player->getKey())
                ->orWhere('player_two_id', $player->getKey()))
            ->count();

        return response()->json([
            'player_id' => $player->getKey(),
            'name' => $player->user?->name ?? 'Unknown',
            'milestones' => $milestones,
            'total_completed_games' => $totalGames,
        ]);
    }
}
