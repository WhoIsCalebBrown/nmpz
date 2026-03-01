<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\JsonResponse;

class PlayerRankPerformanceController extends Controller
{
    public function __invoke(Player $player): JsonResponse
    {
        $playerId = $player->getKey();

        $games = Game::query()
            ->where('status', GameStatus::Completed)
            ->where(fn ($q) => $q->where('player_one_id', $playerId)->orWhere('player_two_id', $playerId))
            ->with(['playerOne', 'playerTwo'])
            ->get(['id', 'player_one_id', 'player_two_id', 'winner_id']);

        $rankStats = [];

        foreach ($games as $game) {
            $isP1 = $game->player_one_id === $playerId;
            $opponent = $isP1 ? $game->playerTwo : $game->playerOne;

            if (! $opponent) {
                continue;
            }

            $opponentRank = $opponent->rank;

            if (! isset($rankStats[$opponentRank])) {
                $rankStats[$opponentRank] = ['games' => 0, 'wins' => 0, 'losses' => 0];
            }

            $rankStats[$opponentRank]['games']++;

            if ($game->winner_id === $playerId) {
                $rankStats[$opponentRank]['wins']++;
            } elseif ($game->winner_id !== null) {
                $rankStats[$opponentRank]['losses']++;
            }
        }

        // Ensure all ranks are represented, in order
        $allRanks = ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond', 'Master'];
        $result = [];

        foreach ($allRanks as $rank) {
            $data = $rankStats[$rank] ?? ['games' => 0, 'wins' => 0, 'losses' => 0];
            $result[] = [
                'rank' => $rank,
                'games_played' => $data['games'],
                'wins' => $data['wins'],
                'losses' => $data['losses'],
                'win_rate' => $data['games'] > 0 ? round($data['wins'] / $data['games'] * 100, 1) : 0,
            ];
        }

        return response()->json([
            'player_id' => $playerId,
            'rank_performance' => $result,
        ]);
    }
}
