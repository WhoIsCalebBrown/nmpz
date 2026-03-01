import StatsPanel from '@/components/ui/StatsPanel';
import { useApiClient } from '@/hooks/useApiClient';
import { useAsyncData } from '@/hooks/useAsyncData';
import type { RecordEntry, Records } from '@/types/stats';

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
    const { data: records, loading } = useAsyncData<Records>(() => api.fetchGlobalRecords(), []);

    if (loading) return <div className="text-xs text-white/30">Loading records...</div>;
    if (!records) return <div className="text-xs text-white/30">No records available</div>;

    const entries = Object.entries(records).filter(
        (pair): pair is [string, RecordEntry] => pair[1] !== null,
    );

    return (
        <StatsPanel title="Server Records">
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
        </StatsPanel>
    );
}
