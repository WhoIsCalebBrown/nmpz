<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HeadToHeadController extends Controller
{
    public function __invoke(Player $player, Player $opponent): JsonResponse
    {
        $playerId = $player->getKey();
        $opponentId = $opponent->getKey();

        $games = Game::query()
            ->where('status', GameStatus::Completed)
            ->where(function ($q) use ($playerId, $opponentId) {
                $q->where(function ($q2) use ($playerId, $opponentId) {
                    $q2->where('player_one_id', $playerId)->where('player_two_id', $opponentId);
                })->orWhere(function ($q2) use ($playerId, $opponentId) {
                    $q2->where('player_one_id', $opponentId)->where('player_two_id', $playerId);
                });
            })
            ->orderByDesc('created_at')
            ->get();

        $playerWins = $games->where('winner_id', $playerId)->count();
        $opponentWins = $games->where('winner_id', $opponentId)->count();
        $draws = $games->count() - $playerWins - $opponentWins;

        // Calculate average scores from rounds
        $roundStats = DB::table('rounds')
            ->join('games', 'games.id', '=', 'rounds.game_id')
            ->where('games.status', GameStatus::Completed->value)
            ->where(function ($q) use ($playerId, $opponentId) {
                $q->where(function ($q2) use ($playerId, $opponentId) {
                    $q2->where('games.player_one_id', $playerId)->where('games.player_two_id', $opponentId);
                })->orWhere(function ($q2) use ($playerId, $opponentId) {
                    $q2->where('games.player_one_id', $opponentId)->where('games.player_two_id', $playerId);
                });
            })
            ->whereNotNull('rounds.finished_at')
            ->selectRaw('
                SUM(CASE WHEN games.player_one_id = ? THEN rounds.player_one_score ELSE rounds.player_two_score END) as player_total_score,
                SUM(CASE WHEN games.player_one_id = ? THEN rounds.player_two_score ELSE rounds.player_one_score END) as opponent_total_score,
                COUNT(*) as total_rounds
            ', [$playerId, $playerId])
            ->first();

        $totalRounds = (int) ($roundStats->total_rounds ?? 0);

        // Recent games (last 5)
        $recentGames = $games->take(5)->map(fn (Game $g) => [
            'game_id' => $g->getKey(),
            'winner_id' => $g->winner_id,
            'match_format' => $g->match_format,
            'played_at' => $g->created_at->toIso8601String(),
        ])->values();

        return response()->json([
            'player' => [
                'player_id' => $playerId,
                'name' => $player->user?->name ?? 'Unknown',
                'elo_rating' => $player->elo_rating,
                'rank' => $player->rank,
            ],
            'opponent' => [
                'player_id' => $opponentId,
                'name' => $opponent->user?->name ?? 'Unknown',
                'elo_rating' => $opponent->elo_rating,
                'rank' => $opponent->rank,
            ],
            'total_games' => $games->count(),
            'player_wins' => $playerWins,
            'opponent_wins' => $opponentWins,
            'draws' => $draws,
            'total_rounds' => $totalRounds,
            'player_avg_score' => $totalRounds > 0 ? round((int) $roundStats->player_total_score / $totalRounds) : 0,
            'opponent_avg_score' => $totalRounds > 0 ? round((int) $roundStats->opponent_total_score / $totalRounds) : 0,
            'recent_games' => $recentGames,
        ]);
    }
}
