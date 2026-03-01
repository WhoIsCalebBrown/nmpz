import StatsPanel from '@/components/ui/StatsPanel';
import { useApiClient } from '@/hooks/useApiClient';
import { useAsyncData } from '@/hooks/useAsyncData';
import type { RivalEntry, RivalsData } from '@/types/stats';

function RivalCard({
    label,
    color,
    rival,
    onViewProfile,
}: {
    label: string;
    color: string;
    rival: NonNullable<RivalEntry>;
    onViewProfile?: (id: string) => void;
}) {
    return (
        <button
            type="button"
            onClick={() => onViewProfile?.(rival.player_id)}
            className={`w-full rounded border p-2 text-left transition hover:bg-white/5 ${color}`}
        >
            <div className="text-[9px] font-semibold uppercase opacity-60">{label}</div>
            <div className="text-[11px] text-white/80">{rival.name}</div>
            <div className="mt-1 flex items-center gap-2 text-[9px]">
                <span className="text-green-400/70">{rival.wins}W</span>
                <span className="text-red-400/70">{rival.losses}L</span>
                <span className="text-white/30">({rival.games_played}g)</span>
                <span className="text-white/50">{rival.win_rate}%</span>
            </div>
        </button>
    );
}

export default function PlayerRivalsPanel({
    playerId,
    onViewProfile,
}: {
    playerId: string;
    onViewProfile?: (id: string) => void;
}) {
    const api = useApiClient(playerId);
    const { data, loading } = useAsyncData<RivalsData>(() => api.fetchPlayerRivals(playerId), []);

    if (loading) return <div className="text-xs text-white/30">Loading rivals...</div>;

    const hasAny = data?.most_played || data?.nemesis || data?.best_matchup;

    return (
        <StatsPanel title="Your Rivals">
            {!hasAny ? (
                <div className="py-4 text-center text-xs text-white/30">Play more games to find rivals</div>
            ) : (
                <div className="space-y-2">
                    {data?.most_played && (
                        <RivalCard
                            label="Most Played"
                            color="border-blue-400/15 bg-blue-400/5"
                            rival={data.most_played}
                            onViewProfile={onViewProfile}
                        />
                    )}
                    {data?.nemesis && (
                        <RivalCard
                            label="Nemesis"
                            color="border-red-400/15 bg-red-400/5"
                            rival={data.nemesis}
                            onViewProfile={onViewProfile}
                        />
                    )}
                    {data?.best_matchup && (
                        <RivalCard
                            label="Best Matchup"
                            color="border-green-400/15 bg-green-400/5"
                            rival={data.best_matchup}
                            onViewProfile={onViewProfile}
                        />
                    )}
                </div>
            )}
        </StatsPanel>
    );
}
