<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\JsonResponse;

class PlayerNemesisHistoryController extends Controller
{
    public function __invoke(Player $player): JsonResponse
    {
        $playerId = $player->getKey();

        // Find the nemesis (opponent with lowest win rate, min 3 games)
        $games = Game::query()
            ->where('status', GameStatus::Completed)
            ->where(fn ($q) => $q->where('player_one_id', $playerId)->orWhere('player_two_id', $playerId))
            ->get(['id', 'player_one_id', 'player_two_id', 'winner_id']);

        $opponents = [];
        foreach ($games as $game) {
            $opponentId = $game->player_one_id === $playerId
                ? $game->player_two_id
                : $game->player_one_id;

            if (! isset($opponents[$opponentId])) {
                $opponents[$opponentId] = ['games' => 0, 'wins' => 0];
            }

            $opponents[$opponentId]['games']++;
            if ($game->winner_id === $playerId) {
                $opponents[$opponentId]['wins']++;
            }
        }

        // Filter to min 3 games, find lowest win rate
        $nemesisId = collect($opponents)
            ->filter(fn ($s) => $s['games'] >= 3)
            ->sortBy(fn ($s) => $s['wins'] / $s['games'])
            ->keys()
            ->first();

        if (! $nemesisId) {
            return response()->json([
                'player_id' => $playerId,
                'nemesis' => null,
                'games' => [],
            ]);
        }

        $nemesis = Player::with('user')->find($nemesisId);
        $nemesisStats = $opponents[$nemesisId];

        // Get the full game history against nemesis
        $h2hGames = Game::query()
            ->where('status', GameStatus::Completed)
            ->where(function ($q) use ($playerId, $nemesisId) {
                $q->where(function ($q2) use ($playerId, $nemesisId) {
                    $q2->where('player_one_id', $playerId)->where('player_two_id', $nemesisId);
                })->orWhere(function ($q2) use ($playerId, $nemesisId) {
                    $q2->where('player_one_id', $nemesisId)->where('player_two_id', $playerId);
                });
            })
            ->with('map')
            ->orderByDesc('created_at')
            ->get();

        $gameHistory = $h2hGames->map(function (Game $game) use ($playerId) {
            $won = $game->winner_id === $playerId;
            $draw = $game->winner_id === null;

            return [
                'game_id' => $game->getKey(),
                'result' => $draw ? 'draw' : ($won ? 'win' : 'loss'),
                'match_format' => $game->match_format,
                'map_name' => $game->map?->display_name ?? $game->map?->name ?? 'Unknown',
                'played_at' => $game->created_at->toIso8601String(),
            ];
        })->values()->all();

        return response()->json([
            'player_id' => $playerId,
            'nemesis' => [
                'player_id' => $nemesisId,
                'name' => $nemesis?->user?->name ?? 'Unknown',
                'elo_rating' => $nemesis?->elo_rating ?? 1000,
                'rank' => $nemesis?->rank ?? 'Bronze',
                'total_games' => $nemesisStats['games'],
                'player_wins' => $nemesisStats['wins'],
                'nemesis_wins' => $nemesisStats['games'] - $nemesisStats['wins'],
                'win_rate' => round($nemesisStats['wins'] / $nemesisStats['games'] * 100, 1),
            ],
            'games' => $gameHistory,
        ]);
    }
}
