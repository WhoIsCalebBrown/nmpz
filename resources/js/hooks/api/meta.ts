import type { AxiosInstance } from 'axios';

export function metaApi(client: AxiosInstance, playerId: string) {
    return {
        fetchStats: () => client.get('/stats'),
        fetchGlobalStats: () => client.get('/stats/dashboard'),
        fetchLeaderboardMovers: () => client.get('/leaderboard/movers'),
        fetchLeaderboard: (options?: { sort?: string; rank?: string }) => {
            const params = new URLSearchParams();
            if (options?.sort) params.set('sort', options.sort);
            if (options?.rank) params.set('rank', options.rank);
            const qs = params.toString();
            return client.get(`/leaderboard${qs ? `?${qs}` : ''}`);
        },
        fetchPlayerStats: () => client.get(`/players/${playerId}/stats`),
        fetchMaps: () => client.get('/maps'),
        fetchMapLeaderboard: (mapId: string) => client.get(`/maps/${mapId}/leaderboard`),
        fetchMapStats: (mapId: string) => client.get(`/maps/${mapId}/stats`),
        fetchTopPlayersByMap: () => client.get('/maps/top-players'),
        fetchGameHistory: (page = 1, filters?: { opponent?: string; map?: string; format?: string; result?: string; from?: string; to?: string }) => {
            const params = new URLSearchParams();
            params.set('page', String(page));
            if (filters?.opponent) params.set('opponent', filters.opponent);
            if (filters?.map) params.set('map', filters.map);
            if (filters?.format) params.set('format', filters.format);
            if (filters?.result) params.set('result', filters.result);
            if (filters?.from) params.set('from', filters.from);
            if (filters?.to) params.set('to', filters.to);
            return client.get(`/players/${playerId}/games?${params.toString()}`);
        },
        fetchGameDetail: (gameId: string) => client.get(`/games/${gameId}/history`),
        fetchGameSummary: (gameId: string) => client.get(`/games/${gameId}/summary`),
        fetchGameRounds: (gameId: string) => client.get(`/games/${gameId}/rounds`),
        fetchAchievements: () => client.get(`/players/${playerId}/achievements`),
        fetchLobbyStats: () => client.get('/lobby/stats'),
        fetchLiveGames: () => client.get('/games/live'),
        fetchRecentWinners: () => client.get('/games/recent-winners'),
        fetchCurrentSeason: () => client.get('/seasons/current'),
        fetchSeasonLeaderboard: (seasonId: string) => client.get(`/seasons/${seasonId}/leaderboard`),
        fetchSeasonHistory: () => client.get('/seasons/history'),
        fetchFeaturedMatch: () => client.get('/games/featured'),
        fetchReplay: (gameId: string) => client.get(`/games/${gameId}/replay`),
        sendSpectatorChat: (gameId: string, playerName: string, message: string) =>
            client.post(`/games/${gameId}/spectator-chat`, { player_name: playerName, message }),
        fetchGameReport: (gameId: string) => client.get(`/games/${gameId}/report`),
        fetchMapDifficulty: () => client.get('/maps/difficulty'),
        fetchMatchmakingStats: () => client.get('/matchmaking/stats'),
        fetchCommunityHighlights: () => client.get('/community/highlights'),
        fetchGlobalRecords: () => client.get('/stats/records'),
        fetchActivePlayers: () => client.get('/leaderboard/active'),
        fetchQueueStatus: () => client.get('/queue/status'),
    };
}
