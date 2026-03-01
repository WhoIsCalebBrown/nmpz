export type GlobalStatsData = {
    total_games: number;
    total_rounds: number;
    total_players: number;
    active_players_7d: number;
    average_round_score: number;
    solo_games_played: number;
    daily_challenges_completed: number;
    games_per_day: { date: string; count: number }[];
    rank_distribution: Record<string, number>;
    popular_maps: { name: string; games: number }[];
};

export type MatchmakingData = {
    total_games: number;
    average_elo_gap: number;
    upset_rate: number;
    balance_score: number;
    gap_distribution: Record<string, number>;
};

export type FormatStat = {
    format: string;
    games_played: number;
    wins: number;
    losses: number;
    draws: number;
    win_rate: number;
};

export type NotableStreak = {
    length: number;
    start_date: string;
    end_date: string;
    active: boolean;
};

export type StreaksData = {
    current_streak: number;
    best_streak: number;
    notable_streaks: NotableStreak[];
};

export type MapEntry = {
    map_id: string;
    map_name: string;
    games_played: number;
    wins: number;
    win_rate: number;
};

export type FavoriteMapsData = {
    most_played: MapEntry | null;
    best_win_rate: MapEntry | null;
    all_maps: MapEntry[];
};

export type MapDifficulty = {
    map_id: string;
    name: string;
    difficulty: string;
    average_score: number;
    total_rounds: number;
    total_games: number;
    perfect_round_rate: number;
};

export type RecordEntry = {
    player_name: string;
    player_id: string;
    value: number;
};

export type Records = {
    longest_win_streak: RecordEntry | null;
    highest_elo: RecordEntry | null;
    most_perfect_rounds: RecordEntry | null;
    most_games_played: RecordEntry | null;
    highest_single_round_score: RecordEntry | null;
    closest_guess: RecordEntry | null;
};

export type Milestone = {
    type: string;
    label: string;
    value: number;
    icon: string;
};

export type MilestonesData = {
    milestones: Milestone[];
    total_completed_games: number;
};

export type Mover = {
    player_id: string;
    name: string;
    net_change: number;
    elo_rating: number;
    rank: string;
    games_played: number;
};

export type MoversData = {
    climbers: Mover[];
    fallers: Mover[];
};

export type ActivePlayer = {
    player_id: string;
    name: string;
    elo_rating: number;
    rank: string;
    games_played: number;
    wins: number;
    win_rate: number;
};

export type Highlights = {
    play_of_the_day: {
        player_id: string;
        player_name: string;
        score: number;
        game_id: string;
        round_number: number;
    } | null;
    rising_stars: {
        player_id: string;
        player_name: string;
        elo_rating: number;
        elo_change: number;
        games_played: number;
    }[];
    hottest_rivalry: {
        player_one: { id: string; name: string; wins: number };
        player_two: { id: string; name: string; wins: number };
        total_games: number;
    } | null;
};

export type RivalEntry = {
    player_id: string;
    name: string;
    elo_rating: number;
    games_played: number;
    wins: number;
    losses: number;
    win_rate: number;
} | null;

export type RivalsData = {
    most_played: RivalEntry;
    nemesis: RivalEntry;
    best_matchup: RivalEntry;
};
