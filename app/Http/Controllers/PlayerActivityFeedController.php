<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\EloHistory;
use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerAchievement;
use App\Models\SoloGame;
use Illuminate\Http\JsonResponse;

class PlayerActivityFeedController extends Controller
{
    public function __invoke(Player $player): JsonResponse
    {
        $playerId = $player->getKey();

        // Recent multiplayer games
        $recentGames = Game::query()
            ->where('status', GameStatus::Completed)
            ->where(fn ($q) => $q->where('player_one_id', $playerId)->orWhere('player_two_id', $playerId))
            ->with(['playerOne.user', 'playerTwo.user'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(function (Game $game) use ($playerId) {
                $isPlayerOne = $game->player_one_id === $playerId;
                $opponent = $isPlayerOne ? $game->playerTwo : $game->playerOne;

                return [
                    'type' => 'game',
                    'game_id' => $game->getKey(),
                    'opponent_name' => $opponent?->user?->name ?? 'Unknown',
                    'result' => $game->winner_id === $playerId ? 'win' : ($game->winner_id === null ? 'draw' : 'loss'),
                    'match_format' => $game->match_format ?? 'classic',
                    'timestamp' => $game->updated_at->toIso8601String(),
                ];
            });

        // Recent elo changes
        $eloChanges = EloHistory::query()
            ->where('player_id', $playerId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($e) => [
                'type' => 'elo_change',
                'game_id' => $e->game_id,
                'rating_before' => $e->rating_before,
                'rating_after' => $e->rating_after,
                'rating_change' => $e->rating_change,
                'timestamp' => $e->created_at->toIso8601String(),
            ]);

        // Recent achievements
        $achievements = PlayerAchievement::query()
            ->where('player_id', $playerId)
            ->with('achievement')
            ->orderByDesc('earned_at')
            ->limit(10)
            ->get()
            ->map(fn ($pa) => [
                'type' => 'achievement',
                'key' => $pa->achievement->key,
                'name' => $pa->achievement->name,
                'icon' => $pa->achievement->icon,
                'timestamp' => $pa->earned_at->toIso8601String(),
            ]);

        // Recent solo games
        $soloGames = SoloGame::query()
            ->where('player_id', $playerId)
            ->where('status', 'completed')
            ->orderByDesc('completed_at')
            ->limit(10)
            ->get()
            ->map(fn ($sg) => [
                'type' => 'solo_game',
                'mode' => $sg->mode,
                'total_score' => $sg->total_score,
                'timestamp' => $sg->completed_at?->toIso8601String() ?? $sg->updated_at->toIso8601String(),
            ]);

        // Merge and sort by timestamp
        $feed = collect()
            ->merge($recentGames)
            ->merge($eloChanges)
            ->merge($achievements)
            ->merge($soloGames)
            ->sortByDesc('timestamp')
            ->take(20)
            ->values()
            ->all();

        return response()->json(['feed' => $feed]);
    }
}
