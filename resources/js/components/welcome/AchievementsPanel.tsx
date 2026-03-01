import StatsPanel from '@/components/ui/StatsPanel';
import type { Achievement } from '@/components/welcome/types';
import { useApiClient } from '@/hooks/useApiClient';
import { useAsyncData } from '@/hooks/useAsyncData';

export default function AchievementsPanel({ playerId }: { playerId: string }) {
    const api = useApiClient(playerId);
    const { data, loading } = useAsyncData<Achievement[]>(() => api.fetchAchievements(), []);
    const achievements = data ?? [];

    if (loading) {
        return (
            <StatsPanel className="max-w-md">
                <div className="py-4 text-center text-xs text-white/30">Loading...</div>
            </StatsPanel>
        );
    }

    const earned = achievements.filter((a) => a.earned_at !== null).length;

    return (
        <StatsPanel
            title="Achievements"
            titleRight={<span className="text-xs text-white/60">{earned} / {achievements.length}</span>}
            className="max-w-md"
        >
            <div className="grid grid-cols-2 gap-2">
                {achievements.map((a) => (
                    <div
                        key={a.key}
                        className={`rounded border p-2 text-xs ${
                            a.earned_at
                                ? 'border-amber-500/30 bg-amber-500/10 text-white'
                                : 'border-white/5 bg-white/5 text-white/30'
                        }`}
                    >
                        <div className="font-bold">{a.name}</div>
                        <div className="mt-0.5 text-[10px] opacity-70">{a.description}</div>
                    </div>
                ))}
            </div>
        </StatsPanel>
    );
}
