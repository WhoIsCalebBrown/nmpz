import StatsPanel from '@/components/ui/StatsPanel';
import { useApiClient } from '@/hooks/useApiClient';
import { useAsyncData } from '@/hooks/useAsyncData';
import type { FavoriteMapsData } from '@/types/stats';

export default function FavoriteMapsPanel({ playerId }: { playerId: string }) {
    const api = useApiClient(playerId);
    const { data, loading } = useAsyncData<FavoriteMapsData>(() => api.fetchPlayerFavoriteMaps(playerId), []);

    if (loading) return <div className="text-xs text-white/30">Loading maps...</div>;
    if (!data || data.all_maps.length === 0) {
        return (
            <StatsPanel title="Favorite Maps">
                <div className="py-4 text-center text-xs text-white/30">No map data yet</div>
            </StatsPanel>
        );
    }

    return (
        <StatsPanel title="Favorite Maps">

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
        </StatsPanel>
    );
}
