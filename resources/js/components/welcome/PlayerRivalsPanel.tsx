import { useEffect, useState } from 'react';
import { useApiClient } from '@/hooks/useApiClient';

type RivalEntry = {
    player_id: string;
    name: string;
    elo_rating: number;
    games_played: number;
    wins: number;
    losses: number;
    win_rate: number;
} | null;

type RivalsData = {
    most_played: RivalEntry;
    nemesis: RivalEntry;
    best_matchup: RivalEntry;
};

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
    const [data, setData] = useState<RivalsData | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        void api
            .fetchPlayerRivals(playerId)
            .then((res) => {
                setData(res.data as RivalsData);
                setLoading(false);
            })
            .catch(() => setLoading(false));
    }, []);

    if (loading) return <div className="text-xs text-white/30">Loading rivals...</div>;

    const hasAny = data?.most_played || data?.nemesis || data?.best_matchup;

    return (
        <div className="w-full rounded border border-white/10 bg-black/60 p-3 backdrop-blur-sm">
            <div className="mb-2 text-xs text-white/60">Your Rivals</div>
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
        </div>
    );
}
