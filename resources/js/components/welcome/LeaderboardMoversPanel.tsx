import { useEffect, useState } from 'react';
import { useApiClient } from '@/hooks/useApiClient';

type Mover = {
    player_id: string;
    name: string;
    net_change: number;
    elo_rating: number;
    rank: string;
    games_played: number;
};

type MoversData = {
    climbers: Mover[];
    fallers: Mover[];
};

export default function LeaderboardMoversPanel({
    playerId,
    onViewProfile,
}: {
    playerId: string;
    onViewProfile?: (id: string) => void;
}) {
    const api = useApiClient(playerId);
    const [data, setData] = useState<MoversData | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        void api.fetchLeaderboardMovers().then((res) => {
            setData(res.data as MoversData);
            setLoading(false);
        });
    }, []);

    if (loading) return <div className="text-xs text-white/30">Loading movers...</div>;
    if (!data) return <div className="text-xs text-white/30">No data available</div>;

    const hasMovers = (data.climbers?.length ?? 0) > 0 || (data.fallers?.length ?? 0) > 0;

    return (
        <div className="w-full rounded border border-white/10 bg-black/60 p-3 backdrop-blur-sm">
            <div className="mb-2 text-xs text-white/60">Leaderboard Movers</div>
            {!hasMovers ? (
                <div className="py-4 text-center text-xs text-white/30">No significant movement</div>
            ) : (
                <div className="space-y-3">
                    {(data.climbers?.length ?? 0) > 0 && (
                        <div>
                            <div className="mb-1 text-[10px] font-semibold uppercase text-green-400/60">Biggest Climbers</div>
                            <div className="space-y-1">
                                {data.climbers.map((m) => (
                                    <button
                                        key={m.player_id}
                                        type="button"
                                        onClick={() => onViewProfile?.(m.player_id)}
                                        className="flex w-full items-center justify-between rounded px-2 py-1 text-[10px] transition hover:bg-white/5"
                                    >
                                        <span className="text-white/70">{m.name}</span>
                                        <div className="flex items-center gap-2">
                                            <span className="text-white/30">{m.elo_rating}</span>
                                            <span className="text-green-400">+{m.net_change}</span>
                                        </div>
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                    {(data.fallers?.length ?? 0) > 0 && (
                        <div>
                            <div className="mb-1 text-[10px] font-semibold uppercase text-red-400/60">Biggest Fallers</div>
                            <div className="space-y-1">
                                {data.fallers.map((m) => (
                                    <button
                                        key={m.player_id}
                                        type="button"
                                        onClick={() => onViewProfile?.(m.player_id)}
                                        className="flex w-full items-center justify-between rounded px-2 py-1 text-[10px] transition hover:bg-white/5"
                                    >
                                        <span className="text-white/70">{m.name}</span>
                                        <div className="flex items-center gap-2">
                                            <span className="text-white/30">{m.elo_rating}</span>
                                            <span className="text-red-400">{m.net_change}</span>
                                        </div>
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
