import ProgressBar from '@/components/ui/ProgressBar';
import StatsPanel from '@/components/ui/StatsPanel';
import { useApiClient } from '@/hooks/useApiClient';
import { useAsyncData } from '@/hooks/useAsyncData';
import { FORMAT_LABELS } from '@/lib/colors';
import type { FormatStat } from '@/types/stats';

export default function FormatStatsPanel({ playerId }: { playerId: string }) {
    const api = useApiClient(playerId);
    const { data, loading } = useAsyncData<{ format_stats: FormatStat[] }>(() => api.fetchPlayerFormatStats(playerId), []);
    const stats = data?.format_stats ?? [];

    if (loading) return <div className="text-xs text-white/30">Loading format stats...</div>;

    return (
        <StatsPanel title="Win Rate by Format">
            {stats.length === 0 ? (
                <div className="py-4 text-center text-xs text-white/30">No format data yet</div>
            ) : (
                <div className="space-y-2">
                    {stats.map((s) => (
                        <div key={s.format} className="rounded bg-white/5 p-2">
                            <div className="mb-1 flex items-center justify-between text-[10px]">
                                <span className="text-white/70">{FORMAT_LABELS[s.format] ?? s.format}</span>
                                <span className="text-white/50">{s.win_rate}%</span>
                            </div>
                            <ProgressBar value={s.win_rate} className="mb-1" />
                            <div className="flex justify-between text-[9px] text-white/30">
                                <span>{s.wins}W / {s.losses}L{s.draws > 0 ? ` / ${s.draws}D` : ''}</span>
                                <span>{s.games_played} games</span>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </StatsPanel>
    );
}
