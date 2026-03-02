<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Jobs\ForceEndRound;
use App\Models\Game;
use App\Models\Player;
use App\Services\GameCompletionService;
use Illuminate\Http\JsonResponse;

class ForfeitGame extends Controller
{
    public function __invoke(Player $player, Game $game, GameCompletionService $completionService): JsonResponse
    {
        abort_if($game->status !== GameStatus::InProgress, 422, 'Game is not in progress.');
        abort_if(! $game->hasPlayer($player), 403, 'You are not in this game.');

        $winnerId = $player->getKey() === $game->player_one_id
            ? $game->player_two_id
            : $game->player_one_id;

        $game->update([
            'status' => GameStatus::Completed,
            'winner_id' => $winnerId,
        ]);

        // Cancel any pending ForceEndRound jobs for active rounds
        $activeRound = $game->rounds()->whereNull('finished_at')->latest('round_number')->first();
        if ($activeRound) {
            ForceEndRound::cancelPending($activeRound->getKey());
        }

        $completionService->finalize($game);

        return response()->json(['forfeited' => true]);
    }
}
