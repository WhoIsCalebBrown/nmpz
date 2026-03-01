import { useEffect, useState } from 'react';
import { useApiClient } from '@/hooks/useApiClient';

type MapDifficulty = {
    map_id: string;
    name: string;
    difficulty: string;
    average_score: number;
    total_rounds: number;
    total_games: number;
    perfect_round_rate: number;
};

const DIFFICULTY_COLORS: Record<string, string> = {
    easy: 'text-green-400 bg-green-400/10',
    medium: 'text-amber-400 bg-amber-400/10',
    hard: 'text-orange-400 bg-orange-400/10',
    extreme: 'text-red-400 bg-red-400/10',
};

export default function MapDifficultyPanel({ playerId }: { playerId: string }) {
    const api = useApiClient(playerId);
    const [maps, setMaps] = useState<MapDifficulty[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        void api.fetchMapDifficulty().then((res) => {
            const data = res.data as { maps: MapDifficulty[] };
            setMaps(data.maps ?? []);
            setLoading(false);
        }).catch(() => setLoading(false));
    }, []);

    if (loading) return <div className="text-xs text-white/30">Loading map data...</div>;

    return (
        <div className="w-full rounded border border-white/10 bg-black/60 p-3 backdrop-blur-sm">
            <div className="mb-2 text-xs text-white/60">Map Difficulty</div>
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
        </div>
    );
}
