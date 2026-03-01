<?php

namespace App\Http\Controllers;

use App\Models\EloHistory;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerEloHistoryController extends Controller
{
    public function __invoke(Request $request, Player $player): JsonResponse
    {
        $limit = min((int) ($request->query('limit', 50)), 200);

        $history = EloHistory::query()
            ->where('player_id', $player->getKey())
            ->with('game:id,player_one_id,player_two_id,winner_id,match_format')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($entry) => [
                'game_id' => $entry->game_id,
                'rating_before' => $entry->rating_before,
                'rating_after' => $entry->rating_after,
                'rating_change' => $entry->rating_change,
                'opponent_rating' => $entry->opponent_rating,
                'won' => $entry->game?->winner_id === $player->getKey(),
                'match_format' => $entry->game?->match_format ?? 'classic',
                'date' => $entry->created_at->toDateString(),
                'timestamp' => $entry->created_at->toIso8601String(),
            ]);

        return response()->json([
            'player_id' => $player->getKey(),
            'current_elo' => $player->elo_rating,
            'rank' => $player->rank,
            'history' => $history,
        ]);
    }
}
