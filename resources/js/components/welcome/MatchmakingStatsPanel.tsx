import { useEffect, useState } from 'react';
import { useApiClient } from '@/hooks/useApiClient';

type MatchmakingData = {
    total_games: number;
    average_elo_gap: number;
    upset_rate: number;
    balance_score: number;
    gap_distribution: Record<string, number>;
};

export default function MatchmakingStatsPanel({ playerId }: { playerId: string }) {
    const api = useApiClient(playerId);
    const [data, setData] = useState<MatchmakingData | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        void api.fetchMatchmakingStats().then((res) => {
            setData(res.data as MatchmakingData);
            setLoading(false);
        });
    }, []);

    if (loading) return <div className="text-xs text-white/30">Loading matchmaking stats...</div>;
    if (!data || data.total_games === 0) return <div className="text-xs text-white/30">No matchmaking data yet</div>;

    const balanceColor = data.balance_score >= 80 ? 'text-green-400' : data.balance_score >= 60 ? 'text-amber-400' : 'text-red-400';

    return (
        <div className="w-full rounded border border-white/10 bg-black/60 p-3 backdrop-blur-sm">
            <div className="mb-2 text-xs text-white/60">Matchmaking Quality</div>
            <div className="mb-3 grid grid-cols-2 gap-2">
                <div className="rounded bg-white/5 p-2 text-center">
                    <div className={`text-sm font-semibold ${balanceColor}`}>{data.balance_score}%</div>
                    <div className="text-[10px] text-white/30">Balance Score</div>
                </div>
                <div className="rounded bg-white/5 p-2 text-center">
                    <div className="text-sm font-semibold text-white/70">{data.upset_rate}%</div>
                    <div className="text-[10px] text-white/30">Upset Rate</div>
                </div>
                <div className="rounded bg-white/5 p-2 text-center">
                    <div className="text-sm font-semibold text-white/70">{Math.round(data.average_elo_gap)}</div>
                    <div className="text-[10px] text-white/30">Avg ELO Gap</div>
                </div>
                <div className="rounded bg-white/5 p-2 text-center">
                    <div className="text-sm font-semibold text-white/70">{data.total_games.toLocaleString()}</div>
                    <div className="text-[10px] text-white/30">Games Analyzed</div>
                </div>
            </div>
            {Object.keys(data.gap_distribution).length > 0 && (
                <div>
                    <div className="mb-1 text-[10px] font-semibold uppercase text-white/30">ELO Gap Distribution</div>
                    <div className="space-y-1">
                        {Object.entries(data.gap_distribution).map(([range, count]) => {
                            const pct = data.total_games > 0 ? (count / data.total_games) * 100 : 0;
                            return (
                                <div key={range} className="flex items-center gap-2 text-[10px]">
                                    <span className="w-16 text-white/40">{range}</span>
                                    <div className="flex-1 h-1.5 rounded-full bg-white/10">
                                        <div
                                            className="h-full rounded-full bg-blue-400/50"
                                            style={{ width: `${pct}%` }}
                                        />
                                    </div>
                                    <span className="w-8 text-right text-white/30">{Math.round(pct)}%</span>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}
