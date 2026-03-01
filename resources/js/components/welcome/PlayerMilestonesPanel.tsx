import { useEffect, useState } from 'react';
import { useApiClient } from '@/hooks/useApiClient';

type Milestone = {
    type: string;
    label: string;
    value: number;
    icon: string;
};

type MilestonesData = {
    milestones: Milestone[];
    total_completed_games: number;
};

export default function PlayerMilestonesPanel({ playerId }: { playerId: string }) {
    const api = useApiClient(playerId);
    const [data, setData] = useState<MilestonesData | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        void api.fetchPlayerMilestones(playerId).then((res) => {
            setData(res.data as MilestonesData);
            setLoading(false);
        });
    }, []);

    if (loading) return <div className="text-xs text-white/30">Loading milestones...</div>;
    if (!data || data.milestones.length === 0) {
        return (
            <div className="w-full rounded border border-white/10 bg-black/60 p-3 backdrop-blur-sm">
                <div className="mb-2 text-xs text-white/60">Milestones</div>
                <div className="py-4 text-center text-xs text-white/30">Play more to unlock milestones</div>
            </div>
        );
    }

    return (
        <div className="w-full rounded border border-white/10 bg-black/60 p-3 backdrop-blur-sm">
            <div className="mb-2 flex items-center justify-between">
                <span className="text-xs text-white/60">Milestones</span>
                <span className="text-[10px] text-white/30">{data.total_completed_games} games</span>
            </div>
            <div className="grid grid-cols-2 gap-1.5">
                {data.milestones.map((m) => (
                    <div key={m.type} className="rounded bg-amber-400/5 border border-amber-400/15 p-2 text-center">
                        <div className="text-sm">{m.icon}</div>
                        <div className="text-[10px] font-semibold text-amber-400/80">{m.label}</div>
                        <div className="text-[9px] text-white/30">{m.value.toLocaleString()}</div>
                    </div>
                ))}
            </div>
        </div>
    );
}
