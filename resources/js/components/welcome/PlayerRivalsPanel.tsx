import { useEffect, useState } from 'react';
import { useApiClient } from '@/hooks/useApiClient';

type Rival = {
    player_id: string;
    name: string;
    elo_rating: number;
    rank: string;
    total_games: number;
    wins: number;
    losses: number;
    win_rate: number;
};

export default function PlayerRivalsPanel({
    playerId,
    onViewProfile,
}: {
    playerId: string;
    onViewProfile?: (id: string) => void;
}) {
    const api = useApiClient(playerId);
    const [rivals, setRivals] = useState<Rival[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        void api.fetchPlayerRivals(playerId).then((res) => {
            const data = res.data as { rivals: Rival[] };
            setRivals(data.rivals);
            setLoading(false);
        });
    }, []);

    if (loading) return <div className="text-xs text-white/30">Loading rivals...</div>;

    return (
        <div className="w-full rounded border border-white/10 bg-black/60 p-3 backdrop-blur-sm">
            <div className="mb-2 text-xs text-white/60">Your Rivals</div>
            {rivals.length === 0 ? (
                <div className="py-4 text-center text-xs text-white/30">Play more games to find rivals</div>
            ) : (
                <div className="space-y-1.5">
                    {rivals.map((r) => (
                        <button
                            key={r.player_id}
                            type="button"
                            onClick={() => onViewProfile?.(r.player_id)}
                            className="flex w-full items-center justify-between rounded bg-white/5 px-2 py-1.5 text-[10px] transition hover:bg-white/10"
                        >
                            <div className="flex items-center gap-2">
                                <span className="text-white/80">{r.name}</span>
                                <span className="text-white/25">{r.rank}</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="text-green-400/70">{r.wins}W</span>
                                <span className="text-red-400/70">{r.losses}L</span>
                                <span className="text-white/40">({r.total_games}g)</span>
                                <span className="text-white/60">{r.win_rate}%</span>
                            </div>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
