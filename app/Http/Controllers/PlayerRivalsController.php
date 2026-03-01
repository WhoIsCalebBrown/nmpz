<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PlayerRivalsController extends Controller
{
    public function __invoke(Player $player): JsonResponse
    {
        $playerId = $player->getKey();

        // Get all completed games for this player
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
                $opponents[$opponentId] = ['games' => 0, 'wins' => 0, 'losses' => 0];
            }

            $opponents[$opponentId]['games']++;

            if ($game->winner_id === $playerId) {
                $opponents[$opponentId]['wins']++;
            } elseif ($game->winner_id === $opponentId) {
                $opponents[$opponentId]['losses']++;
            }
        }

        if (empty($opponents)) {
            return response()->json([
                'most_played' => null,
                'nemesis' => null,
                'best_matchup' => null,
            ]);
        }

        // Most played opponent
        $mostPlayedId = collect($opponents)->sortByDesc('games')->keys()->first();

        // Nemesis: opponent with lowest win rate (min 2 games)
        $nemesisId = collect($opponents)
            ->filter(fn ($s) => $s['games'] >= 2)
            ->sortBy(fn ($s) => $s['games'] > 0 ? $s['wins'] / $s['games'] : 0)
            ->keys()
            ->first();

        // Best matchup: opponent with highest win rate (min 2 games)
        $bestMatchupId = collect($opponents)
            ->filter(fn ($s) => $s['games'] >= 2)
            ->sortByDesc(fn ($s) => $s['games'] > 0 ? $s['wins'] / $s['games'] : 0)
            ->keys()
            ->first();

        $allIds = array_unique(array_filter([$mostPlayedId, $nemesisId, $bestMatchupId]));
        $players = Player::with('user')->whereIn('id', $allIds)->get()->keyBy('id');

        return response()->json([
            'most_played' => $mostPlayedId ? $this->formatRival($mostPlayedId, $opponents[$mostPlayedId], $players) : null,
            'nemesis' => $nemesisId ? $this->formatRival($nemesisId, $opponents[$nemesisId], $players) : null,
            'best_matchup' => $bestMatchupId ? $this->formatRival($bestMatchupId, $opponents[$bestMatchupId], $players) : null,
        ]);
    }

    private function formatRival(string $opponentId, array $stats, $players): array
    {
        $opponent = $players->get($opponentId);

        return [
            'player_id' => $opponentId,
            'name' => $opponent?->user?->name ?? 'Unknown',
            'elo_rating' => $opponent?->elo_rating ?? 1000,
            'games_played' => $stats['games'],
            'wins' => $stats['wins'],
            'losses' => $stats['losses'],
            'win_rate' => $stats['games'] > 0 ? round($stats['wins'] / $stats['games'] * 100, 1) : 0,
        ];
    }
}
