<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use Illuminate\Http\JsonResponse;

class RecentWinnersController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $games = Game::query()
            ->where('status', GameStatus::Completed)
            ->whereNotNull('winner_id')
            ->with(['playerOne.user', 'playerTwo.user', 'winner.user'])
            ->orderByDesc('updated_at')
            ->limit(15)
            ->get()
            ->map(function (Game $game) {
                $loser = $game->winner_id === $game->player_one_id
                    ? $game->playerTwo
                    : $game->playerOne;

                return [
                    'game_id' => $game->getKey(),
                    'winner_name' => $game->winner?->user?->name ?? 'Unknown',
                    'winner_id' => $game->winner_id,
                    'winner_elo' => $game->winner?->elo_rating ?? 1000,
                    'loser_name' => $loser?->user?->name ?? 'Unknown',
                    'loser_id' => $loser?->getKey(),
                    'match_format' => $game->match_format ?? 'classic',
                    'finished_at' => $game->updated_at->toIso8601String(),
                ];
            });

        return response()->json(['winners' => $games]);
    }
}
