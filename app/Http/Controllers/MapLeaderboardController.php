<?php

namespace App\Http\Controllers;

use App\CacheKeys;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Map;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class MapLeaderboardController extends Controller
{
    public function __invoke(Map $map): JsonResponse
    {
        $cacheKey = CacheKeys::mapLeaderboard($map->getKey());

        $data = Cache::remember($cacheKey, 300, function () use ($map) {
            $games = Game::query()
                ->where('map_id', $map->getKey())
                ->where('status', GameStatus::Completed)
                ->with('rounds')
                ->get();

            $playerStats = [];

            foreach ($games as $game) {
                $finishedRounds = $game->rounds->whereNotNull('finished_at');

                foreach (['player_one' => $game->player_one_id, 'player_two' => $game->player_two_id] as $prefix => $playerId) {
                    if (! isset($playerStats[$playerId])) {
                        $playerStats[$playerId] = [
                            'games_played' => 0,
                            'games_won' => 0,
                            'total_score' => 0,
                            'rounds_played' => 0,
                            'best_round_score' => 0,
                        ];
                    }

                    $playerStats[$playerId]['games_played']++;
                    if ($game->winner_id === $playerId) {
                        $playerStats[$playerId]['games_won']++;
                    }

                    foreach ($finishedRounds as $round) {
                        $score = $round->{"{$prefix}_score"} ?? 0;
                        $playerStats[$playerId]['total_score'] += $score;
                        $playerStats[$playerId]['rounds_played']++;
                        $playerStats[$playerId]['best_round_score'] = max($playerStats[$playerId]['best_round_score'], $score);
                    }
                }
            }

            // Filter to players with at least 2 games
            $qualified = collect($playerStats)
                ->filter(fn ($s) => $s['games_played'] >= 2)
                ->sortByDesc('total_score')
                ->take(50);

            $playerIds = $qualified->keys()->all();
            $players = Player::with('user')->whereIn('id', $playerIds)->get()->keyBy('id');

            return $qualified->map(function ($s, $playerId) use ($players) {
                $player = $players->get($playerId);

                return [
                    'player_id' => $playerId,
                    'player_name' => $player?->user?->name ?? 'Unknown',
                    'elo_rating' => $player?->elo_rating ?? 1000,
                    'rank' => $player?->rank ?? 'Bronze',
                    'games_played' => $s['games_played'],
                    'games_won' => $s['games_won'],
                    'total_score' => $s['total_score'],
                    'rounds_played' => $s['rounds_played'],
                    'average_score' => $s['rounds_played'] > 0 ? round($s['total_score'] / $s['rounds_played']) : 0,
                    'best_round_score' => $s['best_round_score'],
                ];
            })->values()->all();
        });

        return response()->json([
            'map' => [
                'id' => $map->getKey(),
                'name' => $map->display_name ?? $map->name,
            ],
            'entries' => $data,
        ]);
    }
}
