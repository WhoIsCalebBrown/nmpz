import { useEffect, useState } from 'react';
import SimpleModal from '@/components/ui/simple-modal';
import type { GameDetail } from '@/components/welcome/types';
import { useApiClient } from '@/hooks/useApiClient';
import { formatDistance } from '@/lib/format';

type GameReport = {
    game_id: string;
    lead_changes: number;
    comeback: boolean;
    rounds: {
        round_number: number;
        round_winner: string | null;
        score_gap: number;
        cumulative_p1: number;
        cumulative_p2: number;
    }[];
    closest_round: { round_number: number; score_gap: number } | null;
    biggest_blowout: { round_number: number; score_gap: number } | null;
};

type DetailTab = 'rounds' | 'report';

export default function GameDetailModal({
    gameId,
    playerId,
    open,
    onClose,
}: {
    gameId: string | null;
    playerId: string;
    open: boolean;
    onClose: () => void;
}) {
    const api = useApiClient(playerId);
    const [detail, setDetail] = useState<GameDetail | null>(null);
    const [report, setReport] = useState<GameReport | null>(null);
    const [loading, setLoading] = useState(false);
    const [tab, setTab] = useState<DetailTab>('rounds');

    useEffect(() => {
        if (!gameId || !open) return;
        setLoading(true);
        setReport(null);
        setTab('rounds');
        void api.fetchGameDetail(gameId).then((res) => {
            setDetail(res.data as GameDetail);
            setLoading(false);
        });
    }, [gameId, open]);

    useEffect(() => {
        if (!gameId || !open || tab !== 'report' || report) return;
        api.fetchGameReport(gameId)
            .then((res) => setReport(res.data as GameReport))
            .catch(() => {});
    }, [tab, gameId, open]);

    if (!open) return null;

    const isP1 = detail?.player_one.id === playerId;

    return (
        <SimpleModal open={open} onClose={onClose}>
            {loading || !detail ? (
                <div className="py-6 text-center text-xs text-white/30">Loading...</div>
            ) : (
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <div className="text-sm text-white">
                            {detail.player_one.name} vs {detail.player_two.name}
                        </div>
                        <div className="text-xs text-white/40">{detail.map_name}</div>
                    </div>
                    <div className="text-xs text-white/50">
                        Winner: {detail.winner_id === null ? 'Draw' : detail.winner_id === detail.player_one.id ? detail.player_one.name : detail.player_two.name}
                    </div>

                    {/* Tabs */}
                    <div className="flex gap-0 border-b border-white/10">
                        {(['rounds', 'report'] as const).map((t) => (
                            <button
                                key={t}
                                type="button"
                                onClick={() => setTab(t)}
                                className={`px-3 py-1 text-[10px] transition ${
                                    tab === t ? 'border-b border-white/40 text-white' : 'text-white/30 hover:text-white/50'
                                }`}
                            >
                                {t}
                            </button>
                        ))}
                    </div>

                    {tab === 'rounds' && (
                        <table className="w-full text-xs">
                            <thead>
                                <tr className="text-white/40">
                                    <th className="py-1 text-left font-normal">Rnd</th>
                                    <th className="py-1 text-center font-normal">{isP1 ? 'You' : detail.player_one.name}</th>
                                    <th className="py-1 text-center font-normal">{isP1 ? detail.player_two.name : 'You'}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {detail.rounds.map((r) => (
                                    <tr key={r.round_number} className="border-t border-white/5">
                                        <td className="py-1 text-white/50">{r.round_number}</td>
                                        <td className="py-1 text-center">
                                            <span className="text-white/80">{r.player_one_score?.toLocaleString() ?? '-'}</span>
                                            <span className="ml-1 text-white/30">
                                                {formatDistance(r.player_one_distance_km)}
                                            </span>
                                        </td>
                                        <td className="py-1 text-center">
                                            <span className="text-white/80">{r.player_two_score?.toLocaleString() ?? '-'}</span>
                                            <span className="ml-1 text-white/30">
                                                {formatDistance(r.player_two_distance_km)}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}

                    {tab === 'report' && (
                        <>
                            {!report ? (
                                <div className="py-4 text-center text-xs text-white/30">Loading report...</div>
                            ) : (
                                <div className="space-y-3">
                                    {/* Momentum bar */}
                                    <div>
                                        <div className="mb-1 text-[10px] font-semibold uppercase text-white/30">Momentum</div>
                                        <div className="flex h-6 items-end gap-[2px]">
                                            {report.rounds.map((r) => {
                                                const diff = isP1
                                                    ? r.cumulative_p1 - r.cumulative_p2
                                                    : r.cumulative_p2 - r.cumulative_p1;
                                                const maxDiff = Math.max(
                                                    ...report.rounds.map((rr) => Math.abs(rr.cumulative_p1 - rr.cumulative_p2)),
                                                    1,
                                                );
                                                const pct = Math.abs(diff) / maxDiff;
                                                return (
                                                    <div
                                                        key={r.round_number}
                                                        className={`flex-1 rounded-sm ${diff >= 0 ? 'bg-green-400/50' : 'bg-red-400/50'}`}
                                                        style={{ height: `${Math.max(pct * 100, 8)}%` }}
                                                        title={`R${r.round_number}: ${diff > 0 ? '+' : ''}${diff}`}
                                                    />
                                                );
                                            })}
                                        </div>
                                    </div>

                                    {/* Stats */}
                                    <div className="grid grid-cols-2 gap-2 text-[10px]">
                                        <div className="rounded bg-white/5 p-2 text-center">
                                            <div className="text-white/70">{report.lead_changes}</div>
                                            <div className="text-white/30">Lead Changes</div>
                                        </div>
                                        <div className={`rounded p-2 text-center ${report.comeback ? 'bg-amber-400/10' : 'bg-white/5'}`}>
                                            <div className={report.comeback ? 'text-amber-400' : 'text-white/70'}>
                                                {report.comeback ? 'Yes' : 'No'}
                                            </div>
                                            <div className="text-white/30">Comeback</div>
                                        </div>
                                    </div>

                                    {report.closest_round && (
                                        <div className="text-[10px] text-white/40">
                                            Closest round: R{report.closest_round.round_number} ({report.closest_round.score_gap} pts gap)
                                        </div>
                                    )}
                                    {report.biggest_blowout && (
                                        <div className="text-[10px] text-white/40">
                                            Biggest blowout: R{report.biggest_blowout.round_number} ({report.biggest_blowout.score_gap.toLocaleString()} pts gap)
                                        </div>
                                    )}
                                </div>
                            )}
                        </>
                    )}
                </div>
            )}
        </SimpleModal>
    );
}
