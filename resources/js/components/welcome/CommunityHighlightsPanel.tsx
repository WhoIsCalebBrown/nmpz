import StatsPanel from '@/components/ui/StatsPanel';
import { useApiClient } from '@/hooks/useApiClient';
import { useAsyncData } from '@/hooks/useAsyncData';
import type { Highlights } from '@/types/stats';

export default function CommunityHighlightsPanel({ playerId }: { playerId: string }) {
    const api = useApiClient(playerId);
    const { data, loading } = useAsyncData<Highlights>(() => api.fetchCommunityHighlights(), []);

    if (loading) return <div className="text-xs text-white/30">Loading highlights...</div>;
    if (!data) return <div className="text-xs text-white/30">No highlights available</div>;

    return (
        <StatsPanel title="Community Highlights">
            <div className="space-y-3">
                {data.play_of_the_day && (
                    <div className="rounded bg-amber-400/5 border border-amber-400/20 p-2">
                        <div className="mb-1 text-[10px] font-semibold uppercase text-amber-400/60">Play of the Day</div>
                        <div className="text-xs text-white/80">{data.play_of_the_day.player_name}</div>
                        <div className="text-[10px] text-white/40">
                            {data.play_of_the_day.score.toLocaleString()} pts
                        </div>
                    </div>
                )}

                {(data.rising_stars?.length ?? 0) > 0 && (
                    <div>
                        <div className="mb-1 text-[10px] font-semibold uppercase text-white/30">Rising Stars</div>
                        <div className="space-y-1">
                            {data.rising_stars.map((star) => (
                                <div key={star.player_id} className="flex items-center justify-between text-[10px]">
                                    <span className="text-white/70">{star.player_name}</span>
                                    <span className="text-green-400">+{star.elo_change} ELO</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {data.hottest_rivalry && (
                    <div className="rounded bg-red-400/5 border border-red-400/20 p-2">
                        <div className="mb-1 text-[10px] font-semibold uppercase text-red-400/60">Hottest Rivalry</div>
                        <div className="text-xs text-white/80">
                            {data.hottest_rivalry.player_one.name} vs {data.hottest_rivalry.player_two.name}
                        </div>
                        <div className="text-[10px] text-white/40">
                            {data.hottest_rivalry.total_games} games ({data.hottest_rivalry.player_one.wins}-{data.hottest_rivalry.player_two.wins})
                        </div>
                    </div>
                )}

                {!data.play_of_the_day && (data.rising_stars?.length ?? 0) === 0 && !data.hottest_rivalry && (
                    <div className="py-2 text-center text-xs text-white/30">No highlights yet today</div>
                )}
            </div>
        </StatsPanel>
    );
}
