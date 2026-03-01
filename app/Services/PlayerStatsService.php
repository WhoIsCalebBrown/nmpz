<?php

namespace App\Services;

use App\Models\Game;
use App\Models\PlayerStats;
use App\Models\Round;
use Illuminate\Support\Collection;

class PlayerStatsService
{
    public function recordRound(Game $game, Round $round): void
    {
        $p1Score = $round->player_one_score ?? 0;
        $p2Score = $round->player_two_score ?? 0;
        $damage = abs($p1Score - $p2Score);

        foreach (['player_one' => $game->player_one_id, 'player_two' => $game->player_two_id] as $prefix => $playerId) {
            $stats = PlayerStats::firstOrCreate(['player_id' => $playerId]);

            $myScore = $prefix === 'player_one' ? $p1Score : $p2Score;
            $opponentScore = $prefix === 'player_one' ? $p2Score : $p1Score;
            $guessLat = $round->{"{$prefix}_guess_lat"};
            $guessLng = $round->{"{$prefix}_guess_lng"};
            $hasGuess = $guessLat !== null && $guessLng !== null;

            $stats->total_rounds++;
            $stats->total_score += $myScore;

            if ($myScore > $stats->best_round_score) {
                $stats->best_round_score = $myScore;
            }

            if ($myScore === config('game.max_health') && $hasGuess) {
                $stats->perfect_rounds++;
            }

            if ($hasGuess) {
                $stats->total_guesses_made++;
                $distanceKm = ScoringService::haversineDistanceKm(
                    $round->location_lat,
                    $round->location_lng,
                    $guessLat,
                    $guessLng,
                );
                $stats->total_distance_km += $distanceKm;

                if ($stats->closest_guess_km === null || $distanceKm < $stats->closest_guess_km) {
                    $stats->closest_guess_km = $distanceKm;
                }
            } else {
                $stats->total_guesses_missed++;
            }

            if ($myScore < $opponentScore) {
                $stats->total_damage_taken += $damage;
            } elseif ($myScore > $opponentScore) {
                $stats->total_damage_dealt += $damage;
            }

            $stats->save();
        }
    }

    /**
     * Aggregate win/loss stats per opponent for a player.
     *
     * @return array<string, array{games: int, wins: int, losses: int}>
     */
    public static function opponentAggregates(string $playerId): array
    {
        $games = Game::completed()
            ->forPlayer($playerId)
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

        return $opponents;
    }

    /**
     * Win rate per map for a player. Returns collection of maps with min 3 games.
     *
     * @return Collection<int, array{name: string, games: int, wins: int, win_rate: float}>
     */
    public static function mapPerformance(string $playerId): Collection
    {
        $games = Game::completed()
            ->forPlayer($playerId)
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

        return collect($mapStats)
            ->filter(fn ($s) => $s['games'] >= 3)
            ->map(fn ($s) => array_merge($s, [
                'win_rate' => round($s['wins'] / $s['games'] * 100, 1),
            ]));
    }

    public function recordGameEnd(Game $game): void
    {
        foreach ([$game->player_one_id, $game->player_two_id] as $playerId) {
            $stats = PlayerStats::firstOrCreate(['player_id' => $playerId]);

            $stats->games_played++;

            if ($game->winner_id === $playerId) {
                $stats->games_won++;
                $stats->current_win_streak++;
                if ($stats->current_win_streak > $stats->best_win_streak) {
                    $stats->best_win_streak = $stats->current_win_streak;
                }
            } elseif ($game->winner_id !== null) {
                $stats->games_lost++;
                $stats->current_win_streak = 0;
            }

            $stats->save();
        }
    }
}
