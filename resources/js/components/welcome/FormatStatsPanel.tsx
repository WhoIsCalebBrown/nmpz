import { useEffect, useState } from 'react';
import { useApiClient } from '@/hooks/useApiClient';

type FormatStat = {
    format: string;
    games: number;
    wins: number;
    losses: number;
    win_rate: number;
};

const FORMAT_LABELS: Record<string, string> = {
    classic: 'Classic',
    best_of_3: 'Best of 3',
    best_of_5: 'Best of 5',
    best_of_7: 'Best of 7',
};

export default function FormatStatsPanel({ playerId }: { playerId: string }) {
    const api = useApiClient(playerId);
    const [stats, setStats] = useState<FormatStat[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        void api.fetchPlayerFormatStats(playerId).then((res) => {
            const data = res.data as { formats: FormatStat[] };
            setStats(data.formats);
            setLoading(false);
        });
    }, []);

    if (loading) return <div className="text-xs text-white/30">Loading format stats...</div>;

    return (
        <div className="w-full rounded border border-white/10 bg-black/60 p-3 backdrop-blur-sm">
            <div className="mb-2 text-xs text-white/60">Win Rate by Format</div>
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
                            <div className="mb-1 h-1.5 rounded-full bg-white/10">
                                <div
                                    className="h-full rounded-full bg-blue-400/50"
                                    style={{ width: `${s.win_rate}%` }}
                                />
                            </div>
                            <div className="flex justify-between text-[9px] text-white/30">
                                <span>{s.wins}W / {s.losses}L</span>
                                <span>{s.games} games</span>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
