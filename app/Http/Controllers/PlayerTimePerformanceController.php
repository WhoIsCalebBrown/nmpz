<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\JsonResponse;

class PlayerTimePerformanceController extends Controller
{
    public function __invoke(Player $player): JsonResponse
    {
        $playerId = $player->getKey();

        $games = Game::completed()
            ->forPlayer($playerId)
            ->get(['id', 'winner_id', 'created_at']);

        if ($games->isEmpty()) {
            return response()->json([
                'player_id' => $playerId,
                'time_slots' => [],
                'best_time' => null,
                'worst_time' => null,
            ]);
        }

        // Group by time slots
        $slots = [
            'morning' => ['label' => 'Morning (6am-12pm)', 'start' => 6, 'end' => 12, 'games' => 0, 'wins' => 0],
            'afternoon' => ['label' => 'Afternoon (12pm-6pm)', 'start' => 12, 'end' => 18, 'games' => 0, 'wins' => 0],
            'evening' => ['label' => 'Evening (6pm-12am)', 'start' => 18, 'end' => 24, 'games' => 0, 'wins' => 0],
            'night' => ['label' => 'Night (12am-6am)', 'start' => 0, 'end' => 6, 'games' => 0, 'wins' => 0],
        ];

        foreach ($games as $game) {
            $hour = (int) $game->created_at->format('G');

            $slot = match (true) {
                $hour >= 6 && $hour < 12 => 'morning',
                $hour >= 12 && $hour < 18 => 'afternoon',
                $hour >= 18 => 'evening',
                default => 'night',
            };

            $slots[$slot]['games']++;
            if ($game->winner_id === $playerId) {
                $slots[$slot]['wins']++;
            }
        }

        $timeSlots = [];
        foreach ($slots as $key => $slot) {
            $timeSlots[] = [
                'slot' => $key,
                'label' => $slot['label'],
                'games_played' => $slot['games'],
                'wins' => $slot['wins'],
                'losses' => $slot['games'] - $slot['wins'],
                'win_rate' => $slot['games'] > 0 ? round($slot['wins'] / $slot['games'] * 100, 1) : 0,
            ];
        }

        // Find best/worst with at least 2 games
        $eligible = collect($timeSlots)->filter(fn ($s) => $s['games_played'] >= 2);
        $best = $eligible->sortByDesc('win_rate')->first();
        $worst = $eligible->sortBy('win_rate')->first();

        return response()->json([
            'player_id' => $playerId,
            'time_slots' => $timeSlots,
            'best_time' => $best ? $best['slot'] : null,
            'worst_time' => $worst ? $worst['slot'] : null,
        ]);
    }
}
