import StatsPanel from '@/components/ui/StatsPanel';
import { useApiClient } from '@/hooks/useApiClient';
import { useAsyncData } from '@/hooks/useAsyncData';
import type { GlobalStatsData } from '@/types/stats';

export default function GlobalStatsPanel({ playerId }: { playerId: string }) {
    const api = useApiClient(playerId);
    const { data, loading } = useAsyncData<GlobalStatsData>(() => api.fetchGlobalStats(), []);

    if (loading) return <div className="text-xs text-white/30">Loading global stats...</div>;
    if (!data) return <div className="text-xs text-white/30">No stats available</div>;

    return (
        <StatsPanel title="Global Stats">
            <div className="mb-3 grid grid-cols-3 gap-2">
                {[
                    { label: 'Players', value: data.total_players },
                    { label: 'Active (7d)', value: data.active_players_7d },
                    { label: 'Games', value: data.total_games },
                    { label: 'Rounds', value: data.total_rounds },
                    { label: 'Avg Score', value: data.average_round_score },
                    { label: 'Solo Games', value: data.solo_games_played },
                ].map((s) => (
                    <div key={s.label} className="rounded bg-white/5 p-1.5 text-center">
                        <div className="text-[10px] font-semibold text-white/70">{s.value.toLocaleString()}</div>
                        <div className="text-[9px] text-white/30">{s.label}</div>
                    </div>
                ))}
            </div>

            {data.games_per_day.length > 0 && (
                <div className="mb-3">
                    <div className="mb-1 text-[10px] font-semibold uppercase text-white/30">Games (14 days)</div>
                    <div className="flex h-10 items-end gap-[2px]">
                        {(() => {
                            const counts = data.games_per_day.map((d) => d.count);
                            const max = Math.max(...counts, 1);
                            return data.games_per_day.map((d) => (
                                <div
                                    key={d.date}
                                    className="flex-1 rounded-t bg-blue-400/40"
                                    style={{ height: `${(d.count / max) * 100}%`, minHeight: '2px' }}
                                    title={`${d.date}: ${d.count} games`}
                                />
                            ));
                        })()}
                    </div>
                </div>
            )}

            {Object.keys(data.rank_distribution).length > 0 && (
                <div className="mb-3">
                    <div className="mb-1 text-[10px] font-semibold uppercase text-white/30">Rank Distribution</div>
                    <div className="flex flex-wrap gap-1">
                        {Object.entries(data.rank_distribution).map(([rank, count]) => (
                            <span key={rank} className="rounded bg-white/5 px-1.5 py-0.5 text-[9px] text-white/50">
                                {rank}: {count}
                            </span>
                        ))}
                    </div>
                </div>
            )}

            {data.popular_maps.length > 0 && (
                <div>
                    <div className="mb-1 text-[10px] font-semibold uppercase text-white/30">Popular Maps</div>
                    <div className="space-y-0.5">
                        {data.popular_maps.map((m) => (
                            <div key={m.name} className="flex items-center justify-between text-[10px]">
                                <span className="text-white/60">{m.name}</span>
                                <span className="text-white/30">{m.games} games</span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </StatsPanel>
    );
}
