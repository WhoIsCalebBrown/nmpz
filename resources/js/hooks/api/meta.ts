import type { AxiosInstance } from 'axios';

export function metaApi(client: AxiosInstance, playerId: string) {
    return {
        fetchStats: () => client.get('/stats'),
        fetchGlobalStats: () => client.get('/stats/dashboard'),
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
        fetchGameHistory: (page = 1) => client.get(`/players/${playerId}/games?page=${page}`),
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
    };
}
