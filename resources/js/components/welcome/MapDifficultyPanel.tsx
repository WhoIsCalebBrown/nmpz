import StatsPanel from '@/components/ui/StatsPanel';
import { useApiClient } from '@/hooks/useApiClient';
import { useAsyncData } from '@/hooks/useAsyncData';
import { DIFFICULTY_COLORS } from '@/lib/colors';
import type { MapDifficulty } from '@/types/stats';

export default function MapDifficultyPanel({ playerId }: { playerId: string }) {
    const api = useApiClient(playerId);
    const { data, loading } = useAsyncData<{ maps: MapDifficulty[] }>(() => api.fetchMapDifficulty(), []);
    const maps = data?.maps ?? [];

    if (loading) return <div className="text-xs text-white/30">Loading map data...</div>;

    return (
        <StatsPanel title="Map Difficulty">
            {maps.length === 0 ? (
                <div className="py-4 text-center text-xs text-white/30">No map data yet</div>
            ) : (
                <div className="space-y-1.5">
                    {maps.map((m) => (
                        <div key={m.map_id} className="flex items-center justify-between rounded bg-white/5 px-2 py-1.5 text-[10px]">
                            <div className="flex items-center gap-2">
                                <span className="text-white/70">{m.name}</span>
                                <span className={`rounded px-1.5 py-0.5 text-[9px] font-semibold uppercase ${DIFFICULTY_COLORS[m.difficulty] ?? 'text-white/40'}`}>
                                    {m.difficulty}
                                </span>
                            </div>
                            <div className="flex items-center gap-3 text-white/40">
                                <span>{Math.round(m.average_score).toLocaleString()} avg</span>
                                <span>{m.perfect_round_rate}% perfect</span>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </StatsPanel>
    );
}
