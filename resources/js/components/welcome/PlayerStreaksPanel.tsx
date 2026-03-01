import { useEffect, useState } from 'react';
import { useApiClient } from '@/hooks/useApiClient';

type NotableStreak = {
    length: number;
    start_date: string;
    end_date: string;
    active: boolean;
};

type StreaksData = {
    current_streak: number;
    best_streak: number;
    notable_streaks: NotableStreak[];
};

export default function PlayerStreaksPanel({ playerId }: { playerId: string }) {
    const api = useApiClient(playerId);
    const [data, setData] = useState<StreaksData | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        void api.fetchPlayerStreaks(playerId).then((res) => {
            setData(res.data as StreaksData);
            setLoading(false);
        });
    }, []);

    if (loading) return <div className="text-xs text-white/30">Loading streaks...</div>;
    if (!data) return <div className="text-xs text-white/30">No streak data</div>;

    return (
        <div className="w-full rounded border border-white/10 bg-black/60 p-3 backdrop-blur-sm">
            <div className="mb-2 text-xs text-white/60">Win Streaks</div>
            <div className="mb-3 grid grid-cols-2 gap-2">
                <div className="rounded bg-white/5 p-2 text-center">
                    <div className={`text-sm font-semibold ${data.current_streak > 0 ? 'text-green-400' : 'text-white/50'}`}>
                        {data.current_streak}
                    </div>
                    <div className="text-[10px] text-white/30">Current</div>
                </div>
                <div className="rounded bg-white/5 p-2 text-center">
                    <div className="text-sm font-semibold text-amber-400">{data.best_streak}</div>
                    <div className="text-[10px] text-white/30">Best</div>
                </div>
            </div>
            {data.notable_streaks.length > 0 && (
                <div>
                    <div className="mb-1 text-[10px] font-semibold uppercase text-white/30">Notable Streaks</div>
                    <div className="space-y-1">
                        {data.notable_streaks.map((s, i) => (
                            <div key={i} className="flex items-center justify-between rounded bg-white/5 px-2 py-1 text-[10px]">
                                <div className="flex items-center gap-2">
                                    <span className="font-semibold text-white/70">{s.length}W</span>
                                    {s.active && (
                                        <span className="rounded bg-green-400/15 px-1 py-0.5 text-[8px] text-green-400">ACTIVE</span>
                                    )}
                                </div>
                                <span className="text-white/30">
                                    {new Date(s.start_date).toLocaleDateString()} - {new Date(s.end_date).toLocaleDateString()}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
