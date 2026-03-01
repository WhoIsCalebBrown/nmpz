<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;

class GameReportController extends Controller
{
    public function __invoke(Game $game): JsonResponse
    {
        $game->load(['playerOne.user', 'playerTwo.user', 'map', 'rounds']);

        $rounds = $game->rounds->sortBy('round_number')->values();
        $finishedRounds = $rounds->whereNotNull('finished_at')->values();

        if ($finishedRounds->isEmpty()) {
            return response()->json([
                'game_id' => $game->getKey(),
                'rounds' => [],
                'momentum' => [],
                'comebacks' => 0,
                'lead_changes' => 0,
                'closest_round' => null,
                'biggest_blowout' => null,
            ]);
        }

        $roundDetails = [];
        $momentum = [];
        $p1CumulativeScore = 0;
        $p2CumulativeScore = 0;
        $leadChanges = 0;
        $previousLeader = null;
        $closestRound = null;
        $closestMargin = PHP_INT_MAX;
        $biggestBlowout = null;
        $biggestMargin = 0;

        foreach ($finishedRounds as $round) {
            $p1Score = $round->player_one_score ?? 0;
            $p2Score = $round->player_two_score ?? 0;
            $p1CumulativeScore += $p1Score;
            $p2CumulativeScore += $p2Score;

            $margin = abs($p1Score - $p2Score);
            $roundWinner = $p1Score > $p2Score ? 'player_one'
                : ($p2Score > $p1Score ? 'player_two' : 'draw');

            $p1Distance = null;
            if ($round->player_one_guess_lat !== null && $round->player_one_guess_lng !== null) {
                $p1Distance = round(ScoringService::haversineDistanceKm(
                    $round->location_lat, $round->location_lng,
                    $round->player_one_guess_lat, $round->player_one_guess_lng,
                ), 1);
            }

            $p2Distance = null;
            if ($round->player_two_guess_lat !== null && $round->player_two_guess_lng !== null) {
                $p2Distance = round(ScoringService::haversineDistanceKm(
                    $round->location_lat, $round->location_lng,
                    $round->player_two_guess_lat, $round->player_two_guess_lng,
                ), 1);
            }

            $roundDetails[] = [
                'round_number' => $round->round_number,
                'winner' => $roundWinner,
                'player_one_score' => $p1Score,
                'player_two_score' => $p2Score,
                'margin' => $margin,
                'player_one_distance_km' => $p1Distance,
                'player_two_distance_km' => $p2Distance,
            ];

            // Track momentum (cumulative score leader)
            $currentLeader = $p1CumulativeScore > $p2CumulativeScore ? 'player_one'
                : ($p2CumulativeScore > $p1CumulativeScore ? 'player_two' : 'tied');

            if ($previousLeader !== null && $currentLeader !== $previousLeader && $currentLeader !== 'tied' && $previousLeader !== 'tied') {
                $leadChanges++;
            }
            $previousLeader = $currentLeader;

            $momentum[] = [
                'round_number' => $round->round_number,
                'leader' => $currentLeader,
                'player_one_cumulative' => $p1CumulativeScore,
                'player_two_cumulative' => $p2CumulativeScore,
                'difference' => $p1CumulativeScore - $p2CumulativeScore,
            ];

            // Track closest and biggest blowout rounds
            if ($margin > 0 && $margin < $closestMargin) {
                $closestMargin = $margin;
                $closestRound = $round->round_number;
            }

            if ($margin > $biggestMargin) {
                $biggestMargin = $margin;
                $biggestBlowout = $round->round_number;
            }
        }

        // Count comebacks: times a player was behind in cumulative score but won the game
        $comebacks = 0;
        if ($game->winner_id) {
            $winnerIsP1 = $game->winner_id === $game->player_one_id;
            foreach ($momentum as $m) {
                if ($winnerIsP1 && $m['leader'] === 'player_two') {
                    $comebacks++;
                    break;
                }
                if (! $winnerIsP1 && $m['leader'] === 'player_one') {
                    $comebacks++;
                    break;
                }
            }
        }

        return response()->json([
            'game_id' => $game->getKey(),
            'winner_id' => $game->winner_id,
            'match_format' => $game->match_format,
            'map_name' => $game->map?->display_name ?? $game->map?->name ?? 'Unknown',
            'total_rounds' => $finishedRounds->count(),
            'player_one' => [
                'id' => $game->player_one_id,
                'name' => $game->playerOne?->user?->name ?? 'Unknown',
            ],
            'player_two' => [
                'id' => $game->player_two_id,
                'name' => $game->playerTwo?->user?->name ?? 'Unknown',
            ],
            'rounds' => $roundDetails,
            'momentum' => $momentum,
            'lead_changes' => $leadChanges,
            'comebacks' => $comebacks,
            'closest_round' => $closestRound ? [
                'round_number' => $closestRound,
                'margin' => $closestMargin,
            ] : null,
            'biggest_blowout' => $biggestBlowout ? [
                'round_number' => $biggestBlowout,
                'margin' => $biggestMargin,
            ] : null,
        ]);
    }
}
