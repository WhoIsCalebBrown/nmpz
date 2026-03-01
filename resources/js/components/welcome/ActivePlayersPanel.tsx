import StatsPanel from '@/components/ui/StatsPanel';
import { useApiClient } from '@/hooks/useApiClient';
import { useAsyncData } from '@/hooks/useAsyncData';
import type { ActivePlayer } from '@/types/stats';

export default function ActivePlayersPanel({
    playerId,
    onViewProfile,
}: {
    playerId: string;
    onViewProfile?: (id: string) => void;
}) {
    const api = useApiClient(playerId);
    const { data, loading } = useAsyncData<{ players: ActivePlayer[] }>(() => api.fetchActivePlayers(), []);
    const players = data?.players ?? [];

    if (loading) return <div className="text-xs text-white/30">Loading active players...</div>;

    return (
        <StatsPanel title="Most Active (7 days)">
            {players.length === 0 ? (
                <div className="py-4 text-center text-xs text-white/30">No recent activity</div>
            ) : (
                <div className="max-h-64 space-y-1 overflow-y-auto">
                    {players.map((p, i) => (
                        <button
                            key={p.player_id}
                            type="button"
                            onClick={() => onViewProfile?.(p.player_id)}
                            className="flex w-full items-center justify-between rounded px-2 py-1 text-xs transition hover:bg-white/5"
                        >
                            <div className="flex items-center gap-2">
                                <span className="w-4 text-right text-white/25">{i + 1}</span>
                                <span className="text-white/80">{p.name}</span>
                                <span className="text-[10px] text-white/25">{p.rank}</span>
                            </div>
                            <div className="flex items-center gap-3">
                                <span className="text-white/40">{p.games_played}g</span>
                                <span className="text-green-400/70">{p.wins}W</span>
                                <span className="text-white/50">{p.win_rate}%</span>
                            </div>
                        </button>
                    ))}
                </div>
            )}
        </StatsPanel>
    );
}
