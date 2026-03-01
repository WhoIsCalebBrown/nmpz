<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;

class PlayerGameLogController extends Controller
{
    public function __invoke(Player $player): JsonResponse
    {
        $playerId = $player->getKey();

        $games = Game::query()
            ->where('status', GameStatus::Completed)
            ->where(fn ($q) => $q->where('player_one_id', $playerId)->orWhere('player_two_id', $playerId))
            ->with(['playerOne.user', 'playerTwo.user', 'map', 'rounds'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $log = $games->map(function (Game $game) use ($playerId) {
            $isP1 = $game->player_one_id === $playerId;
            $opponent = $isP1 ? $game->playerTwo : $game->playerOne;
            $won = $game->winner_id === $playerId;

            $rounds = $game->rounds->sortBy('round_number')->values();
            $finishedRounds = $rounds->whereNotNull('finished_at');

            $roundLog = $finishedRounds->map(function ($round) use ($isP1) {
                $myScore = $isP1 ? ($round->player_one_score ?? 0) : ($round->player_two_score ?? 0);
                $oppScore = $isP1 ? ($round->player_two_score ?? 0) : ($round->player_one_score ?? 0);

                $myGuessLat = $isP1 ? $round->player_one_guess_lat : $round->player_two_guess_lat;
                $myGuessLng = $isP1 ? $round->player_one_guess_lng : $round->player_two_guess_lng;

                $distance = null;
                if ($myGuessLat !== null && $myGuessLng !== null) {
                    $distance = round(ScoringService::haversineDistanceKm(
                        $round->location_lat, $round->location_lng,
                        $myGuessLat, $myGuessLng,
                    ), 1);
                }

                return [
                    'round_number' => $round->round_number,
                    'my_score' => $myScore,
                    'opponent_score' => $oppScore,
                    'won_round' => $myScore > $oppScore,
                    'distance_km' => $distance,
                    'perfect' => $myScore >= 5000,
                ];
            })->values()->all();

            return [
                'game_id' => $game->getKey(),
                'opponent_name' => $opponent?->user?->name ?? 'Unknown',
                'opponent_id' => $opponent?->getKey(),
                'result' => $game->winner_id === null ? 'draw' : ($won ? 'win' : 'loss'),
                'match_format' => $game->match_format,
                'map_name' => $game->map?->display_name ?? $game->map?->name ?? 'Unknown',
                'played_at' => $game->updated_at?->toIso8601String(),
                'rounds' => $roundLog,
            ];
        })->all();

        return response()->json([
            'player_id' => $playerId,
            'games' => $log,
        ]);
    }
}
