<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Map;
use App\Models\Round;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MapDifficultyController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $maps = Map::query()->where('is_active', true)->get();

        $results = [];

        foreach ($maps as $map) {
            $mapId = $map->getKey();

            $gameCount = Game::query()
                ->where('map_id', $mapId)
                ->where('status', GameStatus::Completed)
                ->count();

            if ($gameCount === 0) {
                continue;
            }

            $avgScore = (float) Round::query()
                ->whereNotNull('finished_at')
                ->whereHas('game', fn ($q) => $q->where('map_id', $mapId)->where('status', GameStatus::Completed))
                ->selectRaw('AVG(COALESCE(player_one_score, 0) + COALESCE(player_two_score, 0)) / 2 as avg_score')
                ->value('avg_score');

            $perfectRounds = DB::table('rounds')
                ->join('games', 'games.id', '=', 'rounds.game_id')
                ->where('games.map_id', $mapId)
                ->where('games.status', GameStatus::Completed)
                ->whereNotNull('rounds.finished_at')
                ->where(function ($q) {
                    $q->where('rounds.player_one_score', '>=', 5000)
                        ->orWhere('rounds.player_two_score', '>=', 5000);
                })
                ->count();

            $totalRounds = Round::query()
                ->whereNotNull('finished_at')
                ->whereHas('game', fn ($q) => $q->where('map_id', $mapId)->where('status', GameStatus::Completed))
                ->count();

            $perfectRate = $totalRounds > 0 ? round($perfectRounds / $totalRounds * 100, 1) : 0;

            $difficulty = match (true) {
                $avgScore >= 4000 => 'easy',
                $avgScore >= 3000 => 'medium',
                $avgScore >= 2000 => 'hard',
                default => 'extreme',
            };

            $results[] = [
                'map_id' => $mapId,
                'name' => $map->display_name ?? $map->name,
                'difficulty' => $difficulty,
                'average_score' => round($avgScore),
                'perfect_round_rate' => $perfectRate,
                'total_games' => $gameCount,
                'total_rounds' => $totalRounds,
            ];
        }

        usort($results, fn ($a, $b) => $a['average_score'] <=> $b['average_score']);

        return response()->json([
            'maps' => $results,
        ]);
    }
}
