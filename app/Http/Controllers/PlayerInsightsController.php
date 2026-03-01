<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PlayerInsightsController extends Controller
{
    public function __invoke(Player $player): JsonResponse
    {
        $playerId = $player->getKey();
        $stats = $player->stats;

        if (! $stats || $stats->games_played < 3) {
            return response()->json([
                'player_id' => $playerId,
                'insights' => [],
            ]);
        }

        $insights = [];

        // 1. Win streak insight
        if ($stats->current_win_streak >= 3) {
            $insights[] = [
                'type' => 'positive',
                'category' => 'streak',
                'message' => "You're on a {$stats->current_win_streak}-game win streak! Keep it up!",
            ];
        } elseif ($stats->current_win_streak === 0 && $stats->games_played >= 5) {
            $insights[] = [
                'type' => 'tip',
                'category' => 'streak',
                'message' => "Your win streak was broken. Focus on consistency to start a new streak.",
            ];
        }

        // 2. Accuracy insight
        $avgScore = $stats->total_rounds > 0 ? $stats->total_score / $stats->total_rounds : 0;
        if ($avgScore < 2500 && $stats->total_rounds >= 10) {
            $insights[] = [
                'type' => 'tip',
                'category' => 'accuracy',
                'message' => "Your average round score is " . round($avgScore) . ". Try zooming into the map more before guessing to improve accuracy.",
            ];
        } elseif ($avgScore >= 4000) {
            $insights[] = [
                'type' => 'positive',
                'category' => 'accuracy',
                'message' => "Excellent accuracy! Your average round score of " . round($avgScore) . " is very strong.",
            ];
        }

        // 3. Map performance insight
        $mapPerformance = $this->getMapPerformance($playerId);
        if ($mapPerformance['worst'] && $mapPerformance['best']) {
            if ($mapPerformance['worst']['win_rate'] < 30) {
                $insights[] = [
                    'type' => 'tip',
                    'category' => 'map',
                    'message' => "You struggle on {$mapPerformance['worst']['name']} ({$mapPerformance['worst']['win_rate']}% win rate). Consider practicing that region.",
                ];
            }
            if ($mapPerformance['best']['win_rate'] >= 70) {
                $insights[] = [
                    'type' => 'positive',
                    'category' => 'map',
                    'message' => "You dominate on {$mapPerformance['best']['name']} with a {$mapPerformance['best']['win_rate']}% win rate!",
                ];
            }
        }

        // 4. ELO trend insight
        $recentElo = DB::table('elo_history')
            ->where('player_id', $playerId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->pluck('rating_change');

        if ($recentElo->count() >= 5) {
            $totalChange = $recentElo->sum();
            if ($totalChange > 50) {
                $insights[] = [
                    'type' => 'positive',
                    'category' => 'elo',
                    'message' => "Your ELO has risen by {$totalChange} in the last 5 games. You're improving fast!",
                ];
            } elseif ($totalChange < -50) {
                $insights[] = [
                    'type' => 'tip',
                    'category' => 'elo',
                    'message' => "Your ELO dropped by " . abs($totalChange) . " in the last 5 games. Consider playing more carefully or on familiar maps.",
                ];
            }
        }

        // 5. Games played milestone
        $milestones = [500, 250, 100, 50, 25, 10];
        foreach ($milestones as $milestone) {
            if ($stats->games_played >= $milestone && $stats->games_played < $milestone + 3) {
                $insights[] = [
                    'type' => 'positive',
                    'category' => 'milestone',
                    'message' => "Congratulations on reaching {$milestone} games played!",
                ];
                break;
            }
        }

        // 6. Close guess rate
        if ($stats->closest_guess_km !== null && $stats->closest_guess_km < 1) {
            $insights[] = [
                'type' => 'positive',
                'category' => 'precision',
                'message' => "Your closest guess was only " . round($stats->closest_guess_km, 2) . " km away. Incredible precision!",
            ];
        }

        return response()->json([
            'player_id' => $playerId,
            'insights' => $insights,
        ]);
    }

    private function getMapPerformance(string $playerId): array
    {
        $games = Game::query()
            ->where('status', GameStatus::Completed)
            ->where(fn ($q) => $q->where('player_one_id', $playerId)->orWhere('player_two_id', $playerId))
            ->with('map')
            ->get(['id', 'map_id', 'winner_id', 'player_one_id', 'player_two_id']);

        $mapStats = [];
        foreach ($games as $game) {
            $mapName = $game->map?->display_name ?? $game->map?->name ?? 'Unknown';
            $mapId = $game->map_id;

            if (! isset($mapStats[$mapId])) {
                $mapStats[$mapId] = ['name' => $mapName, 'games' => 0, 'wins' => 0];
            }

            $mapStats[$mapId]['games']++;
            if ($game->winner_id === $playerId) {
                $mapStats[$mapId]['wins']++;
            }
        }

        // Filter to maps with at least 3 games
        $mapStats = array_filter($mapStats, fn ($s) => $s['games'] >= 3);

        if (empty($mapStats)) {
            return ['best' => null, 'worst' => null];
        }

        foreach ($mapStats as &$ms) {
            $ms['win_rate'] = round($ms['wins'] / $ms['games'] * 100, 1);
        }

        $best = collect($mapStats)->sortByDesc('win_rate')->first();
        $worst = collect($mapStats)->sortBy('win_rate')->first();

        return ['best' => $best, 'worst' => $worst];
    }
}
