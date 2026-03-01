import { useEffect, useState } from 'react';
import { useApiClient } from '@/hooks/useApiClient';

type RecordEntry = {
    player_name: string;
    player_id: string;
    value: number;
};

type Records = {
    longest_win_streak: RecordEntry | null;
    highest_elo: RecordEntry | null;
    most_perfect_rounds: RecordEntry | null;
    most_games_played: RecordEntry | null;
    highest_single_round_score: RecordEntry | null;
    closest_guess: RecordEntry | null;
};

const RECORD_LABELS: Record<string, string> = {
    longest_win_streak: 'Longest Win Streak',
    highest_elo: 'Highest ELO',
    most_perfect_rounds: 'Most Perfect Rounds',
    most_games_played: 'Most Games Played',
    highest_single_round_score: 'Highest Round Score',
    closest_guess: 'Closest Guess',
};

export default function GlobalRecordsPanel({
    playerId,
    onViewProfile,
}: {
    playerId: string;
    onViewProfile?: (id: string) => void;
}) {
    const api = useApiClient(playerId);
    const [records, setRecords] = useState<Records | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        void api.fetchGlobalRecords().then((res) => {
            setRecords(res.data as Records);
            setLoading(false);
        });
    }, []);

    if (loading) return <div className="text-xs text-white/30">Loading records...</div>;
    if (!records) return <div className="text-xs text-white/30">No records available</div>;

    const entries = Object.entries(records).filter(
        (pair): pair is [string, RecordEntry] => pair[1] !== null,
    );

    return (
        <div className="w-full rounded border border-white/10 bg-black/60 p-3 backdrop-blur-sm">
            <div className="mb-2 text-xs text-white/60">Server Records</div>
            {entries.length === 0 ? (
                <div className="py-4 text-center text-xs text-white/30">No records yet</div>
            ) : (
                <div className="space-y-2">
                    {entries.map(([key, record]) => (
                        <div key={key} className="flex items-center justify-between rounded bg-white/5 px-2 py-1.5">
                            <div>
                                <div className="text-[10px] text-white/30">{RECORD_LABELS[key] ?? key}</div>
                                <button
                                    type="button"
                                    onClick={() => onViewProfile?.(record.player_id)}
                                    className="text-xs text-white/70 hover:text-white"
                                >
                                    {record.player_name}
                                </button>
                            </div>
                            <div className="text-sm font-semibold text-amber-400/80">
                                {record.value.toLocaleString()}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
