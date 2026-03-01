<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:50'],
        ]);

        $query = $validated['q'];

        $players = Player::query()
            ->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$query}%"))
            ->with('user:id,name')
            ->limit(20)
            ->get()
            ->map(fn (Player $p) => [
                'player_id' => $p->getKey(),
                'name' => $p->user?->name ?? 'Unknown',
                'elo_rating' => $p->elo_rating,
                'rank' => $p->rank,
            ]);

        return response()->json($players);
    }
}
