import StatsPanel from '@/components/ui/StatsPanel';
import { useApiClient } from '@/hooks/useApiClient';
import { useAsyncData } from '@/hooks/useAsyncData';
import type { MilestonesData } from '@/types/stats';

export default function PlayerMilestonesPanel({ playerId }: { playerId: string }) {
    const api = useApiClient(playerId);
    const { data, loading } = useAsyncData<MilestonesData>(() => api.fetchPlayerMilestones(playerId), []);

    if (loading) return <div className="text-xs text-white/30">Loading milestones...</div>;
    if (!data || data.milestones.length === 0) {
        return (
            <StatsPanel title="Milestones">
                <div className="py-4 text-center text-xs text-white/30">Play more to unlock milestones</div>
            </StatsPanel>
        );
    }

    return (
        <StatsPanel title="Milestones" titleRight={<span className="text-[10px] text-white/30">{data.total_completed_games} games</span>}>
            <div className="grid grid-cols-2 gap-1.5">
                {data.milestones.map((m) => (
                    <div key={m.type} className="rounded bg-amber-400/5 border border-amber-400/15 p-2 text-center">
                        <div className="text-sm">{m.icon}</div>
                        <div className="text-[10px] font-semibold text-amber-400/80">{m.label}</div>
                        <div className="text-[9px] text-white/30">{m.value.toLocaleString()}</div>
                    </div>
                ))}
            </div>
        </StatsPanel>
    );
}
