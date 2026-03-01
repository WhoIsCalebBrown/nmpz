<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\JsonResponse;

class PlayerStreaksController extends Controller
{
    public function __invoke(Player $player): JsonResponse
    {
        $playerId = $player->getKey();

        $games = Game::query()
            ->where('status', GameStatus::Completed)
            ->where(fn ($q) => $q->where('player_one_id', $playerId)->orWhere('player_two_id', $playerId))
            ->orderBy('updated_at')
            ->get(['id', 'winner_id', 'player_one_id', 'player_two_id', 'updated_at', 'match_format']);

        $streaks = [];
        $currentStreak = [];

        foreach ($games as $game) {
            $won = $game->winner_id === $playerId;

            if ($won) {
                $currentStreak[] = $game;
            } else {
                if (count($currentStreak) >= 2) {
                    $streaks[] = $this->formatStreak($currentStreak, $playerId);
                }
                $currentStreak = [];
            }
        }

        // Don't forget the current streak if still going
        if (count($currentStreak) >= 2) {
            $streaks[] = $this->formatStreak($currentStreak, $playerId, active: true);
        }

        // Sort by length descending
        usort($streaks, fn ($a, $b) => $b['length'] <=> $a['length']);

        // Current streak info
        $currentStreakLength = 0;
        foreach ($games->reverse() as $game) {
            if ($game->winner_id === $playerId) {
                $currentStreakLength++;
            } else {
                break;
            }
        }

        return response()->json([
            'current_streak' => $currentStreakLength,
            'best_streak' => $player->stats?->best_win_streak ?? 0,
            'notable_streaks' => array_slice($streaks, 0, 5),
        ]);
    }

    private function formatStreak(array $games, string $playerId, bool $active = false): array
    {
        $first = $games[0];
        $last = end($games);

        return [
            'length' => count($games),
            'start_date' => $first->updated_at->toDateString(),
            'end_date' => $last->updated_at->toDateString(),
            'active' => $active,
            'game_ids' => array_map(fn ($g) => $g->getKey(), $games),
        ];
    }
}
