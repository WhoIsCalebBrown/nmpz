import { useEffect, useState } from 'react';
import { useApiClient } from '@/hooks/useApiClient';

type MapEntry = {
    map_id: string;
    map_name: string;
    games_played: number;
    wins: number;
    win_rate: number;
};

type FavoriteMapsData = {
    most_played: MapEntry | null;
    best_win_rate: MapEntry | null;
    all_maps: MapEntry[];
};

export default function FavoriteMapsPanel({ playerId }: { playerId: string }) {
    const api = useApiClient(playerId);
    const [data, setData] = useState<FavoriteMapsData | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        void api.fetchPlayerFavoriteMaps(playerId).then((res) => {
            setData(res.data as FavoriteMapsData);
            setLoading(false);
        });
    }, []);

    if (loading) return <div className="text-xs text-white/30">Loading maps...</div>;
    if (!data || data.all_maps.length === 0) {
        return (
            <div className="w-full rounded border border-white/10 bg-black/60 p-3 backdrop-blur-sm">
                <div className="mb-2 text-xs text-white/60">Favorite Maps</div>
                <div className="py-4 text-center text-xs text-white/30">No map data yet</div>
            </div>
        );
    }

    return (
        <div className="w-full rounded border border-white/10 bg-black/60 p-3 backdrop-blur-sm">
            <div className="mb-2 text-xs text-white/60">Favorite Maps</div>

            {(data.most_played || data.best_win_rate) && (
                <div className="mb-3 grid grid-cols-2 gap-2">
                    {data.most_played && (
                        <div className="rounded bg-blue-400/5 border border-blue-400/15 p-2 text-center">
                            <div className="text-[9px] font-semibold uppercase text-blue-400/60">Most Played</div>
                            <div className="text-[10px] text-white/70">{data.most_played.map_name}</div>
                            <div className="text-[9px] text-white/30">{data.most_played.games_played} games</div>
                        </div>
                    )}
                    {data.best_win_rate && (
                        <div className="rounded bg-green-400/5 border border-green-400/15 p-2 text-center">
                            <div className="text-[9px] font-semibold uppercase text-green-400/60">Best Win Rate</div>
                            <div className="text-[10px] text-white/70">{data.best_win_rate.map_name}</div>
                            <div className="text-[9px] text-white/30">{data.best_win_rate.win_rate}%</div>
                        </div>
                    )}
                </div>
            )}

            <div className="space-y-1">
                {data.all_maps.map((m) => (
                    <div key={m.map_id} className="flex items-center justify-between text-[10px]">
                        <span className="text-white/60">{m.map_name}</span>
                        <div className="flex items-center gap-2">
                            <span className="text-white/30">{m.wins}/{m.games_played}</span>
                            <span className="text-white/60">{m.win_rate}%</span>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
