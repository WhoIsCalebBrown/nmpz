import { useEffect, useState } from 'react';
import { useApiClient } from '@/hooks/useApiClient';

type ActivePlayer = {
    player_id: string;
    name: string;
    elo_rating: number;
    rank: string;
    games_played: number;
    wins: number;
    win_rate: number;
};

export default function ActivePlayersPanel({
    playerId,
    onViewProfile,
}: {
    playerId: string;
    onViewProfile?: (id: string) => void;
}) {
    const api = useApiClient(playerId);
    const [players, setPlayers] = useState<ActivePlayer[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        void api.fetchActivePlayers().then((res) => {
            const data = res.data as { players: ActivePlayer[] };
            setPlayers(data.players);
            setLoading(false);
        });
    }, []);

    if (loading) return <div className="text-xs text-white/30">Loading active players...</div>;

    return (
        <div className="w-full rounded border border-white/10 bg-black/60 p-3 backdrop-blur-sm">
            <div className="mb-2 flex items-center justify-between">
                <span className="text-xs text-white/60">Most Active (7 days)</span>
            </div>
            {players.length === 0 ? (
                <div className="py-4 text-center text-xs text-white/30">No recent activity</div>
            ) : (
                <div className="max-h-64 space-y-1 overflow-y-auto">
                    {players.map((p, i) => (
                        <button
                            key={p.player_id}
                            type="button"
                            onClick={() => onViewProfile?.(p.player_id)}
                            className="flex w-full items-center justify-between rounded px-2 py-1 text-xs transition hover:bg-white/5"
                        >
                            <div className="flex items-center gap-2">
                                <span className="w-4 text-right text-white/25">{i + 1}</span>
                                <span className="text-white/80">{p.name}</span>
                                <span className="text-[10px] text-white/25">{p.rank}</span>
                            </div>
                            <div className="flex items-center gap-3">
                                <span className="text-white/40">{p.games_played}g</span>
                                <span className="text-green-400/70">{p.wins}W</span>
                                <span className="text-white/50">{p.win_rate}%</span>
                            </div>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
