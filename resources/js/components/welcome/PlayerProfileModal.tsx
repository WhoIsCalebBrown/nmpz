import { useEffect, useState } from 'react';
import RankBadge from '@/components/welcome/RankBadge';
import type { Rank } from '@/components/welcome/types';
import { useApiClient } from '@/hooks/useApiClient';

type ProfileData = {
    player_id: string;
    name: string;
    elo_rating: number;
    rank: Rank;
    stats: {
        games_played: number;
        games_won: number;
        win_rate: number;
        best_win_streak: number;
        best_round_score: number;
        average_score: number;
    };
    elo_history: { elo: number; date: string }[];
    achievements: { key: string; name: string; icon: string | null; earned_at: string }[];
    map_stats: { map_name: string; wins: number; total_games: number; win_rate: number }[];
};

type SkillProfileData = {
    scores: { accuracy: number; consistency: number; clutch: number; win_rate: number; perfect_rate: number };
    archetype: string;
    games_analyzed: number;
};

type InsightsData = {
    insights: { type: string; message: string; priority: string }[];
};

type NemesisData = {
    nemesis: {
        player_id: string;
        name: string;
        total_games: number;
        player_wins: number;
        nemesis_wins: number;
        win_rate: number;
    } | null;
    games: { game_id: string; result: string; map_name: string; played_at: string }[];
};

type WinTrendsData = {
    windows: { period: string; games: number; wins: number; win_rate: number }[];
    form: string;
};

type ProfileTab = 'overview' | 'skills' | 'insights' | 'nemesis';

type PlayerProfileModalProps = {
    targetPlayerId: string | null;
    playerId: string;
    open: boolean;
    onClose: () => void;
};

const TABS: { key: ProfileTab; label: string }[] = [
    { key: 'overview', label: 'Overview' },
    { key: 'skills', label: 'Skills' },
    { key: 'insights', label: 'Insights' },
    { key: 'nemesis', label: 'Nemesis' },
];

function SkillBar({ label, value }: { label: string; value: number }) {
    return (
        <div className="space-y-0.5">
            <div className="flex justify-between text-[10px]">
                <span className="text-white/50">{label}</span>
                <span className="text-white/70">{value}</span>
            </div>
            <div className="h-1.5 rounded-full bg-white/10">
                <div
                    className="h-full rounded-full bg-blue-400/60"
                    style={{ width: `${value}%` }}
                />
            </div>
        </div>
    );
}

export default function PlayerProfileModal({ targetPlayerId, playerId, open, onClose }: PlayerProfileModalProps) {
    const api = useApiClient(playerId);
    const [tab, setTab] = useState<ProfileTab>('overview');
    const [profile, setProfile] = useState<ProfileData | null>(null);
    const [skills, setSkills] = useState<SkillProfileData | null>(null);
    const [insights, setInsights] = useState<InsightsData | null>(null);
    const [nemesis, setNemesis] = useState<NemesisData | null>(null);
    const [winTrends, setWinTrends] = useState<WinTrendsData | null>(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!open || !targetPlayerId) return;
        setLoading(true);
        setProfile(null);
        setSkills(null);
        setInsights(null);
        setNemesis(null);
        setWinTrends(null);
        setTab('overview');
        api.fetchPlayerProfile(targetPlayerId)
            .then((res) => setProfile(res.data as ProfileData))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, [open, targetPlayerId]);

    useEffect(() => {
        if (!open || !targetPlayerId) return;
        if (tab === 'skills' && !skills) {
            api.fetchPlayerSkillProfile(targetPlayerId)
                .then((res) => setSkills(res.data as SkillProfileData))
                .catch(() => {});
            api.fetchPlayerWinTrends(targetPlayerId)
                .then((res) => setWinTrends(res.data as WinTrendsData))
                .catch(() => {});
        }
        if (tab === 'insights' && !insights) {
            api.fetchPlayerInsights(targetPlayerId)
                .then((res) => setInsights(res.data as InsightsData))
                .catch(() => {});
        }
        if (tab === 'nemesis' && !nemesis) {
            api.fetchPlayerNemesis(targetPlayerId)
                .then((res) => setNemesis(res.data as NemesisData))
                .catch(() => {});
        }
    }, [tab, open, targetPlayerId]);

    if (!open) return null;

    const resultColor = (r: string) =>
        r === 'win' ? 'text-green-400' : r === 'loss' ? 'text-red-400' : 'text-white/40';

    const priorityColor = (p: string) =>
        p === 'high' ? 'border-amber-400/30 bg-amber-400/5' : p === 'medium' ? 'border-blue-400/30 bg-blue-400/5' : 'border-white/10 bg-white/5';

    const formColor = (f: string) =>
        f === 'hot' ? 'text-green-400' : f === 'cold' ? 'text-red-400' : 'text-white/40';

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" onClick={onClose}>
            <div
                className="w-full max-w-md rounded border border-white/10 bg-neutral-900 p-5 font-mono text-sm text-white shadow-2xl"
                onClick={(e) => e.stopPropagation()}
            >
                {loading ? (
                    <div className="py-8 text-center text-white/40">Loading profile...</div>
                ) : !profile ? (
                    <div className="py-8 text-center text-white/40">Player not found</div>
                ) : (
                    <>
                        {/* Header */}
                        <div className="mb-3 flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <span className="text-lg font-semibold">{profile.name}</span>
                                <RankBadge rank={profile.rank} elo={profile.elo_rating} />
                            </div>
                            <button type="button" onClick={onClose} className="text-white/30 transition hover:text-white">
                                x
                            </button>
                        </div>

                        {/* Tabs */}
                        <div className="mb-3 flex gap-0 border-b border-white/10">
                            {TABS.map(({ key, label }) => (
                                <button
                                    key={key}
                                    type="button"
                                    onClick={() => setTab(key)}
                                    className={`px-3 py-1 text-[10px] transition ${
                                        tab === key
                                            ? 'border-b border-white/40 text-white'
                                            : 'text-white/30 hover:text-white/50'
                                    }`}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>

                        {/* Tab Content */}
                        <div className="max-h-72 overflow-y-auto">
                            {tab === 'overview' && (
                                <>
                                    <div className="mb-4 grid grid-cols-3 gap-2">
                                        {[
                                            { label: 'Games', value: profile.stats.games_played },
                                            { label: 'Wins', value: profile.stats.games_won },
                                            { label: 'Win Rate', value: `${profile.stats.win_rate}%` },
                                            { label: 'Best Streak', value: profile.stats.best_win_streak },
                                            { label: 'Best Round', value: profile.stats.best_round_score.toLocaleString() },
                                            { label: 'Avg Score', value: Math.round(profile.stats.average_score).toLocaleString() },
                                        ].map((s) => (
                                            <div key={s.label} className="rounded bg-white/5 p-2 text-center">
                                                <div className="text-xs font-semibold text-white/80">{s.value}</div>
                                                <div className="text-[10px] text-white/30">{s.label}</div>
                                            </div>
                                        ))}
                                    </div>

                                    {profile.elo_history.length > 1 && (
                                        <div className="mb-4">
                                            <div className="mb-1 text-[10px] font-semibold uppercase text-white/30">ELO History</div>
                                            <div className="flex h-12 items-end gap-[2px]">
                                                {(() => {
                                                    const values = profile.elo_history.map((e) => e.elo);
                                                    const min = Math.min(...values);
                                                    const max = Math.max(...values);
                                                    const range = max - min || 1;
                                                    return values.map((v, i) => (
                                                        <div
                                                            key={i}
                                                            className="flex-1 rounded-t bg-blue-400/40"
                                                            style={{ height: `${((v - min) / range) * 100}%`, minHeight: '2px' }}
                                                            title={`${v} - ${profile.elo_history[i].date}`}
                                                        />
                                                    ));
                                                })()}
                                            </div>
                                        </div>
                                    )}

                                    {profile.achievements.length > 0 && (
                                        <div className="mb-4">
                                            <div className="mb-1 text-[10px] font-semibold uppercase text-white/30">Recent Achievements</div>
                                            <div className="flex flex-wrap gap-1">
                                                {profile.achievements.map((a) => (
                                                    <span
                                                        key={a.key}
                                                        className="rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] text-amber-400"
                                                        title={a.name}
                                                    >
                                                        {a.icon ?? ''} {a.name}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {profile.map_stats.length > 0 && (
                                        <div>
                                            <div className="mb-1 text-[10px] font-semibold uppercase text-white/30">Map Win Rates</div>
                                            <div className="space-y-1">
                                                {profile.map_stats.map((m) => (
                                                    <div key={m.map_name} className="flex items-center justify-between text-[10px]">
                                                        <span className="text-white/60">{m.map_name}</span>
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-white/30">{m.wins}/{m.total_games}</span>
                                                            <span className="text-white/80">{m.win_rate}%</span>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </>
                            )}

                            {tab === 'skills' && (
                                <>
                                    {!skills ? (
                                        <div className="py-4 text-center text-xs text-white/30">Loading...</div>
                                    ) : skills.games_analyzed === 0 ? (
                                        <div className="py-4 text-center text-xs text-white/30">Not enough games for skill analysis</div>
                                    ) : (
                                        <>
                                            <div className="mb-3 flex items-center justify-between">
                                                <span className="text-[10px] uppercase text-white/30">Archetype</span>
                                                <span className="rounded bg-blue-400/15 px-2 py-0.5 text-xs text-blue-400">
                                                    {skills.archetype}
                                                </span>
                                            </div>
                                            <div className="mb-4 space-y-2">
                                                <SkillBar label="Accuracy" value={skills.scores.accuracy} />
                                                <SkillBar label="Consistency" value={skills.scores.consistency} />
                                                <SkillBar label="Clutch" value={skills.scores.clutch} />
                                                <SkillBar label="Win Rate" value={skills.scores.win_rate} />
                                                <SkillBar label="Perfect Rate" value={skills.scores.perfect_rate} />
                                            </div>
                                            <div className="text-[10px] text-white/30">
                                                Based on {skills.games_analyzed} games analyzed
                                            </div>
                                        </>
                                    )}
                                    {winTrends && (
                                        <div className="mt-4 border-t border-white/10 pt-3">
                                            <div className="mb-2 flex items-center justify-between">
                                                <span className="text-[10px] uppercase text-white/30">Form</span>
                                                <span className={`text-xs font-semibold ${formColor(winTrends.form)}`}>
                                                    {winTrends.form}
                                                </span>
                                            </div>
                                            <div className="space-y-1">
                                                {winTrends.windows.map((w) => (
                                                    <div key={w.period} className="flex items-center justify-between text-[10px]">
                                                        <span className="text-white/50">{w.period}</span>
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-white/30">{w.wins}W / {w.games - w.wins}L</span>
                                                            <span className="text-white/70">{w.win_rate}%</span>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </>
                            )}

                            {tab === 'insights' && (
                                <>
                                    {!insights ? (
                                        <div className="py-4 text-center text-xs text-white/30">Loading...</div>
                                    ) : insights.insights.length === 0 ? (
                                        <div className="py-4 text-center text-xs text-white/30">Play more games to unlock insights</div>
                                    ) : (
                                        <div className="space-y-2">
                                            {insights.insights.map((insight, i) => (
                                                <div
                                                    key={i}
                                                    className={`rounded border p-2 ${priorityColor(insight.priority)}`}
                                                >
                                                    <div className="mb-0.5 flex items-center gap-2">
                                                        <span className="text-[10px] font-semibold uppercase text-white/40">
                                                            {insight.type}
                                                        </span>
                                                    </div>
                                                    <div className="text-xs text-white/70">{insight.message}</div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </>
                            )}

                            {tab === 'nemesis' && (
                                <>
                                    {!nemesis ? (
                                        <div className="py-4 text-center text-xs text-white/30">Loading...</div>
                                    ) : !nemesis.nemesis ? (
                                        <div className="py-4 text-center text-xs text-white/30">No nemesis yet (need 3+ games vs same opponent)</div>
                                    ) : (
                                        <>
                                            <div className="mb-3 rounded bg-red-400/5 border border-red-400/20 p-3">
                                                <div className="mb-1 text-xs font-semibold text-red-400">
                                                    {nemesis.nemesis.name}
                                                </div>
                                                <div className="grid grid-cols-3 gap-2 text-center text-[10px]">
                                                    <div>
                                                        <div className="text-white/70">{nemesis.nemesis.total_games}</div>
                                                        <div className="text-white/30">Games</div>
                                                    </div>
                                                    <div>
                                                        <div className="text-green-400">{nemesis.nemesis.player_wins}W</div>
                                                        <div className="text-white/30">Your Wins</div>
                                                    </div>
                                                    <div>
                                                        <div className="text-red-400">{nemesis.nemesis.nemesis_wins}W</div>
                                                        <div className="text-white/30">Their Wins</div>
                                                    </div>
                                                </div>
                                                <div className="mt-2 text-center text-[10px] text-white/40">
                                                    Win rate: {nemesis.nemesis.win_rate}%
                                                </div>
                                            </div>
                                            {nemesis.games.length > 0 && (
                                                <div>
                                                    <div className="mb-1 text-[10px] font-semibold uppercase text-white/30">Match History</div>
                                                    <div className="space-y-1">
                                                        {nemesis.games.slice(0, 10).map((g) => (
                                                            <div key={g.game_id} className="flex items-center justify-between text-[10px]">
                                                                <span className={`font-bold uppercase ${resultColor(g.result)}`}>
                                                                    {g.result === 'win' ? 'W' : g.result === 'loss' ? 'L' : 'D'}
                                                                </span>
                                                                <span className="text-white/40">{g.map_name}</span>
                                                                <span className="text-white/25">
                                                                    {new Date(g.played_at).toLocaleDateString()}
                                                                </span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                        </>
                                    )}
                                </>
                            )}
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}
