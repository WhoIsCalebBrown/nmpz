<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PlayerFormatStatsController extends Controller
{
    public function __invoke(Player $player): JsonResponse
    {
        $playerId = $player->getKey();

        $stats = DB::table('games')
            ->where('status', GameStatus::Completed->value)
            ->where(fn ($q) => $q->where('player_one_id', $playerId)->orWhere('player_two_id', $playerId))
            ->selectRaw("
                COALESCE(match_format, 'classic') as format,
                COUNT(*) as games_played,
                SUM(CASE WHEN winner_id = ? THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN winner_id IS NOT NULL AND winner_id != ? THEN 1 ELSE 0 END) as losses,
                SUM(CASE WHEN winner_id IS NULL THEN 1 ELSE 0 END) as draws
            ", [$playerId, $playerId])
            ->groupBy('format')
            ->orderByDesc('games_played')
            ->get()
            ->map(fn ($row) => [
                'format' => $row->format,
                'games_played' => (int) $row->games_played,
                'wins' => (int) $row->wins,
                'losses' => (int) $row->losses,
                'draws' => (int) $row->draws,
                'win_rate' => $row->games_played > 0 ? round($row->wins / $row->games_played * 100, 1) : 0,
            ]);

        return response()->json([
            'player_id' => $playerId,
            'format_stats' => $stats,
        ]);
    }
}
