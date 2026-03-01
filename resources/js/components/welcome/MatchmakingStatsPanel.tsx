import ProgressBar from '@/components/ui/ProgressBar';
import StatGrid from '@/components/ui/StatGrid';
import StatsPanel from '@/components/ui/StatsPanel';
import { useApiClient } from '@/hooks/useApiClient';
import { useAsyncData } from '@/hooks/useAsyncData';
import type { MatchmakingData } from '@/types/stats';

export default function MatchmakingStatsPanel({ playerId }: { playerId: string }) {
    const api = useApiClient(playerId);
    const { data, loading } = useAsyncData<MatchmakingData>(() => api.fetchMatchmakingStats(), []);

    if (loading) return <div className="text-xs text-white/30">Loading matchmaking stats...</div>;
    if (!data || data.total_games === 0) return <div className="text-xs text-white/30">No matchmaking data yet</div>;

    const balanceColor = data.balance_score >= 80 ? 'text-green-400' : data.balance_score >= 60 ? 'text-amber-400' : 'text-red-400';

    return (
        <StatsPanel title="Matchmaking Quality">
            <StatGrid
                className="mb-3"
                items={[
                    { label: 'Balance Score', value: `${data.balance_score}%`, className: balanceColor },
                    { label: 'Upset Rate', value: `${data.upset_rate}%` },
                    { label: 'Avg ELO Gap', value: Math.round(data.average_elo_gap) },
                    { label: 'Games Analyzed', value: data.total_games },
                ]}
            />
            {Object.keys(data.gap_distribution).length > 0 && (
                <div>
                    <div className="mb-1 text-[10px] font-semibold uppercase text-white/30">ELO Gap Distribution</div>
                    <div className="space-y-1">
                        {Object.entries(data.gap_distribution).map(([range, count]) => {
                            const pct = data.total_games > 0 ? (count / data.total_games) * 100 : 0;
                            return (
                                <div key={range} className="flex items-center gap-2 text-[10px]">
                                    <span className="w-16 text-white/40">{range}</span>
                                    <ProgressBar value={pct} className="flex-1" />
                                    <span className="w-8 text-right text-white/30">{Math.round(pct)}%</span>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}
        </StatsPanel>
    );
}
