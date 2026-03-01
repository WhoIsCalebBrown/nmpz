import type { Rank } from '@/types/player';

export type ProfileData = {
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

export type SkillProfileData = {
    scores: { accuracy: number; consistency: number; clutch: number; win_rate: number; perfect_rate: number };
    archetype: string;
    games_analyzed: number;
};

export type InsightsData = {
    insights: { type: string; message: string; priority: string }[];
};

export type NemesisData = {
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

export type WinTrendsData = {
    windows: { period: string; games: number; wins: number; win_rate: number }[];
    form: string;
};

export type RankPerformanceData = {
    ranks: { rank: string; games: number; wins: number; win_rate: number }[];
};

export type TimePerformanceData = {
    slots: { slot: string; games: number; wins: number; win_rate: number }[];
};

export type GameLogEntry = {
    game_id: string;
    result: string;
    opponent_name: string;
    map_name: string;
    my_score: number;
    opponent_score: number;
    played_at: string;
    rounds: { round_number: number; score: number; distance_km: number }[];
};
