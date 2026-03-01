<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;

class GameRoundsController extends Controller
{
    public function __invoke(Game $game): JsonResponse
    {
        $game->load(['rounds' => fn ($q) => $q->orderBy('round_number'), 'playerOne.user', 'playerTwo.user']);

        $rounds = $game->rounds
            ->filter(fn ($round) => $round->finished_at !== null)
            ->map(function ($round) {
                $p1Distance = null;
                $p2Distance = null;

                if ($round->player_one_guess_lat !== null && $round->player_one_guess_lng !== null) {
                    $p1Distance = round(ScoringService::haversineDistanceKm(
                        $round->location_lat, $round->location_lng,
                        $round->player_one_guess_lat, $round->player_one_guess_lng,
                    ), 2);
                }

                if ($round->player_two_guess_lat !== null && $round->player_two_guess_lng !== null) {
                    $p2Distance = round(ScoringService::haversineDistanceKm(
                        $round->location_lat, $round->location_lng,
                        $round->player_two_guess_lat, $round->player_two_guess_lng,
                    ), 2);
                }

                return [
                    'round_number' => $round->round_number,
                    'location' => [
                        'lat' => $round->location_lat,
                        'lng' => $round->location_lng,
                    ],
                    'player_one' => [
                        'score' => $round->player_one_score ?? 0,
                        'guess' => $round->player_one_guess_lat !== null ? [
                            'lat' => $round->player_one_guess_lat,
                            'lng' => $round->player_one_guess_lng,
                        ] : null,
                        'distance_km' => $p1Distance,
                        'locked_in' => $round->player_one_locked_in,
                    ],
                    'player_two' => [
                        'score' => $round->player_two_score ?? 0,
                        'guess' => $round->player_two_guess_lat !== null ? [
                            'lat' => $round->player_two_guess_lat,
                            'lng' => $round->player_two_guess_lng,
                        ] : null,
                        'distance_km' => $p2Distance,
                        'locked_in' => $round->player_two_locked_in,
                    ],
                    'started_at' => $round->started_at?->toIso8601String(),
                    'finished_at' => $round->finished_at?->toIso8601String(),
                ];
            })
            ->values();

        return response()->json([
            'game_id' => $game->getKey(),
            'player_one' => [
                'id' => $game->player_one_id,
                'name' => $game->playerOne?->user?->name ?? 'Unknown',
            ],
            'player_two' => [
                'id' => $game->player_two_id,
                'name' => $game->playerTwo?->user?->name ?? 'Unknown',
            ],
            'rounds' => $rounds,
        ]);
    }
}
