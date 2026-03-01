<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;

class GameSummaryController extends Controller
{
    public function __invoke(Game $game): JsonResponse
    {
        $game->load(['playerOne.user', 'playerTwo.user', 'map', 'rounds']);

        $rounds = $game->rounds->sortBy('round_number')->values();
        $finishedRounds = $rounds->whereNotNull('finished_at');

        $p1Scores = $finishedRounds->pluck('player_one_score')->filter(fn ($s) => $s !== null);
        $p2Scores = $finishedRounds->pluck('player_two_score')->filter(fn ($s) => $s !== null);

        $p1Distances = $finishedRounds->map(function ($r) {
            if ($r->player_one_guess_lat === null) {
                return null;
            }

            return ScoringService::haversineDistanceKm(
                $r->location_lat, $r->location_lng,
                $r->player_one_guess_lat, $r->player_one_guess_lng,
            );
        })->filter(fn ($d) => $d !== null);

        $p2Distances = $finishedRounds->map(function ($r) {
            if ($r->player_two_guess_lat === null) {
                return null;
            }

            return ScoringService::haversineDistanceKm(
                $r->location_lat, $r->location_lng,
                $r->player_two_guess_lat, $r->player_two_guess_lng,
            );
        })->filter(fn ($d) => $d !== null);

        $p1RoundWins = $finishedRounds->filter(fn ($r) => ($r->player_one_score ?? 0) > ($r->player_two_score ?? 0))->count();
        $p2RoundWins = $finishedRounds->filter(fn ($r) => ($r->player_two_score ?? 0) > ($r->player_one_score ?? 0))->count();

        return response()->json([
            'game_id' => $game->getKey(),
            'winner_id' => $game->winner_id,
            'match_format' => $game->match_format,
            'map_name' => $game->map?->display_name ?? $game->map?->name ?? 'Unknown',
            'total_rounds' => $finishedRounds->count(),
            'player_one' => [
                'id' => $game->player_one_id,
                'name' => $game->playerOne?->user?->name ?? 'Unknown',
                'elo_rating' => $game->playerOne?->elo_rating ?? 1000,
                'rank' => $game->playerOne?->rank ?? 'Bronze',
                'rating_change' => $game->player_one_rating_change,
                'total_score' => $p1Scores->sum(),
                'average_score' => $p1Scores->count() > 0 ? round($p1Scores->avg()) : 0,
                'best_round_score' => $p1Scores->max() ?? 0,
                'worst_round_score' => $p1Scores->min() ?? 0,
                'rounds_won' => $p1RoundWins,
                'average_distance_km' => $p1Distances->count() > 0 ? round($p1Distances->avg(), 1) : null,
                'closest_guess_km' => $p1Distances->count() > 0 ? round($p1Distances->min(), 1) : null,
                'total_health_remaining' => $game->player_one_health,
            ],
            'player_two' => [
                'id' => $game->player_two_id,
                'name' => $game->playerTwo?->user?->name ?? 'Unknown',
                'elo_rating' => $game->playerTwo?->elo_rating ?? 1000,
                'rank' => $game->playerTwo?->rank ?? 'Bronze',
                'rating_change' => $game->player_two_rating_change,
                'total_score' => $p2Scores->sum(),
                'average_score' => $p2Scores->count() > 0 ? round($p2Scores->avg()) : 0,
                'best_round_score' => $p2Scores->max() ?? 0,
                'worst_round_score' => $p2Scores->min() ?? 0,
                'rounds_won' => $p2RoundWins,
                'average_distance_km' => $p2Distances->count() > 0 ? round($p2Distances->avg(), 1) : null,
                'closest_guess_km' => $p2Distances->count() > 0 ? round($p2Distances->min(), 1) : null,
                'total_health_remaining' => $game->player_two_health,
            ],
        ]);
    }
}
