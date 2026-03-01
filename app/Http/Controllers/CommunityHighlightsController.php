<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\EloHistory;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CommunityHighlightsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'play_of_the_day' => $this->playOfTheDay(),
            'rising_stars' => $this->risingStars(),
            'hottest_rivalry' => $this->hottestRivalry(),
        ]);
    }

    private function playOfTheDay(): ?array
    {
        $today = now()->startOfDay();

        // Best single round score today
        $bestRound = DB::table('rounds')
            ->join('games', 'games.id', '=', 'rounds.game_id')
            ->where('rounds.finished_at', '>=', $today)
            ->whereNotNull('rounds.finished_at')
            ->selectRaw('
                rounds.game_id,
                rounds.round_number,
                CASE
                    WHEN COALESCE(rounds.player_one_score, 0) >= COALESCE(rounds.player_two_score, 0)
                    THEN games.player_one_id
                    ELSE games.player_two_id
                END as player_id,
                CASE
                    WHEN COALESCE(rounds.player_one_score, 0) >= COALESCE(rounds.player_two_score, 0)
                    THEN COALESCE(rounds.player_one_score, 0)
                    ELSE COALESCE(rounds.player_two_score, 0)
                END as best_score
            ')
            ->orderByDesc('best_score')
            ->first();

        if (! $bestRound || $bestRound->best_score === 0) {
            return null;
        }

        $player = Player::with('user')->find($bestRound->player_id);

        return [
            'player_id' => $bestRound->player_id,
            'player_name' => $player?->user?->name ?? 'Unknown',
            'score' => (int) $bestRound->best_score,
            'game_id' => $bestRound->game_id,
            'round_number' => (int) $bestRound->round_number,
        ];
    }

    private function risingStars(): array
    {
        // Players with biggest ELO gains in the last 7 days who have <30 games
        $stars = DB::table('elo_history')
            ->join('players', 'players.id', '=', 'elo_history.player_id')
            ->join('users', 'users.id', '=', 'players.user_id')
            ->leftJoin('player_stats', 'player_stats.player_id', '=', 'players.id')
            ->where('elo_history.created_at', '>=', now()->subDays(7))
            ->where(function ($q) {
                $q->where('player_stats.games_played', '<', 30)
                    ->orWhereNull('player_stats.games_played');
            })
            ->groupBy('elo_history.player_id', 'users.name', 'players.elo_rating', 'player_stats.games_played')
            ->selectRaw('
                elo_history.player_id,
                users.name as player_name,
                players.elo_rating,
                COALESCE(player_stats.games_played, 0) as games_played,
                SUM(elo_history.rating_change) as elo_change
            ')
            ->orderByDesc('elo_change')
            ->limit(5)
            ->get();

        return $stars->map(fn ($s) => [
            'player_id' => $s->player_id,
            'player_name' => $s->player_name,
            'elo_rating' => (int) $s->elo_rating,
            'elo_change' => (int) $s->elo_change,
            'games_played' => (int) $s->games_played,
        ])->all();
    }

    private function hottestRivalry(): ?array
    {
        // Most games between any two players in the last 14 days
        $rivalry = Game::completed()
            ->where('created_at', '>=', now()->subDays(14))
            ->selectRaw('
                CASE WHEN player_one_id < player_two_id THEN player_one_id ELSE player_two_id END as p1,
                CASE WHEN player_one_id < player_two_id THEN player_two_id ELSE player_one_id END as p2,
                COUNT(*) as game_count
            ')
            ->groupBy('p1', 'p2')
            ->orderByDesc('game_count')
            ->first();

        if (! $rivalry || $rivalry->game_count < 2) {
            return null;
        }

        $players = Player::with('user')->whereIn('id', [$rivalry->p1, $rivalry->p2])->get()->keyBy('id');

        // Get win counts
        $p1Wins = Game::completed()
            ->where('created_at', '>=', now()->subDays(14))
            ->betweenPlayers($rivalry->p1, $rivalry->p2)
            ->where('winner_id', $rivalry->p1)
            ->count();

        return [
            'player_one' => [
                'id' => $rivalry->p1,
                'name' => $players->get($rivalry->p1)?->user?->name ?? 'Unknown',
                'wins' => $p1Wins,
            ],
            'player_two' => [
                'id' => $rivalry->p2,
                'name' => $players->get($rivalry->p2)?->user?->name ?? 'Unknown',
                'wins' => (int) $rivalry->game_count - $p1Wins,
            ],
            'total_games' => (int) $rivalry->game_count,
        ];
    }
}
