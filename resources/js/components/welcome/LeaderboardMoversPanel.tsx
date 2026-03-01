import StatsPanel from '@/components/ui/StatsPanel';
import { useApiClient } from '@/hooks/useApiClient';
import { useAsyncData } from '@/hooks/useAsyncData';
import type { MoversData } from '@/types/stats';

export default function LeaderboardMoversPanel({
    playerId,
    onViewProfile,
}: {
    playerId: string;
    onViewProfile?: (id: string) => void;
}) {
    const api = useApiClient(playerId);
    const { data, loading } = useAsyncData<MoversData>(() => api.fetchLeaderboardMovers(), []);

    if (loading) return <div className="text-xs text-white/30">Loading movers...</div>;
    if (!data) return <div className="text-xs text-white/30">No data available</div>;

    const hasMovers = (data.climbers?.length ?? 0) > 0 || (data.fallers?.length ?? 0) > 0;

    return (
        <StatsPanel title="Leaderboard Movers">
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
        </StatsPanel>
    );
}
