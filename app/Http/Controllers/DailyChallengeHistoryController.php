<?php

namespace App\Http\Controllers;

use App\Models\DailyChallenge;
use Illuminate\Http\JsonResponse;

class DailyChallengeHistoryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $challenges = DailyChallenge::query()
            ->where('challenge_date', '<', today())
            ->with('map')
            ->withCount('entries')
            ->orderByDesc('challenge_date')
            ->limit(30)
            ->get()
            ->map(function (DailyChallenge $challenge) {
                $topEntry = $challenge->entries()
                    ->whereNotNull('completed_at')
                    ->orderByDesc('total_score')
                    ->with('player.user')
                    ->first();

                return [
                    'id' => $challenge->getKey(),
                    'date' => $challenge->challenge_date->toDateString(),
                    'map_name' => $challenge->map?->display_name ?? $challenge->map?->name ?? 'Unknown',
                    'participants' => $challenge->entries_count,
                    'top_player' => $topEntry ? [
                        'name' => $topEntry->player?->user?->name ?? 'Unknown',
                        'player_id' => $topEntry->player_id,
                        'score' => $topEntry->total_score,
                    ] : null,
                ];
            });

        return response()->json(['challenges' => $challenges]);
    }
}
