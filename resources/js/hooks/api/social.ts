import type { AxiosInstance } from 'axios';

export function socialApi(client: AxiosInstance, playerId: string) {
    return {
        fetchPlayerProfile: (targetPlayerId: string) => client.get(`/players/${targetPlayerId}/profile`),
        fetchPlayerMilestones: (targetPlayerId: string) => client.get(`/players/${targetPlayerId}/milestones`),
        fetchPlayerActivityFeed: (targetPlayerId: string) => client.get(`/players/${targetPlayerId}/activity-feed`),
        fetchPlayerStreaks: (targetPlayerId: string) => client.get(`/players/${targetPlayerId}/streaks`),
        fetchPlayerEloHistory: (targetPlayerId: string, limit?: number) =>
            client.get(`/players/${targetPlayerId}/elo-history${limit ? `?limit=${limit}` : ''}`),
        fetchPlayerRanking: (targetPlayerId: string) => client.get(`/players/${targetPlayerId}/ranking`),
        fetchPlayerRecords: (targetPlayerId: string) => client.get(`/players/${targetPlayerId}/records`),
        fetchPlayerRivals: (targetPlayerId: string) => client.get(`/players/${targetPlayerId}/rivals`),
        fetchPlayerFavoriteMaps: (targetPlayerId: string) => client.get(`/players/${targetPlayerId}/favorite-maps`),
        fetchPlayerFormatStats: (targetPlayerId: string) => client.get(`/players/${targetPlayerId}/format-stats`),
        fetchFriends: () => client.get(`/players/${playerId}/friends`),
        sendFriendRequest: (receiverId: string) =>
            client.post(`/players/${playerId}/friends`, { receiver_id: receiverId }),
        acceptFriendRequest: (friendshipId: string) =>
            client.post(`/players/${playerId}/friends/${friendshipId}/accept`),
        declineFriendRequest: (friendshipId: string) =>
            client.post(`/players/${playerId}/friends/${friendshipId}/decline`),
        removeFriend: (friendshipId: string) =>
            client.delete(`/players/${playerId}/friends/${friendshipId}`),
        fetchPendingFriends: () => client.get(`/players/${playerId}/friends/pending`),
        searchPlayers: (query: string) => client.get(`/players/search?q=${encodeURIComponent(query)}`),
        fetchHeadToHead: (opponentId: string) => client.get(`/players/${playerId}/head-to-head/${opponentId}`),
        fetchHeadToHeadMaps: (opponentId: string) => client.get(`/players/${playerId}/head-to-head/${opponentId}/maps`),
        compareWithPlayer: (opponentId: string) => client.get(`/players/${playerId}/compare/${opponentId}`),
        fetchPlayerWinTrends: (targetPlayerId: string) => client.get(`/players/${targetPlayerId}/win-trends`),
        fetchPlayerSkillProfile: (targetPlayerId: string) => client.get(`/players/${targetPlayerId}/skill-profile`),
        fetchPlayerInsights: (targetPlayerId: string) => client.get(`/players/${targetPlayerId}/insights`),
        fetchPlayerRankPerformance: (targetPlayerId: string) => client.get(`/players/${targetPlayerId}/rank-performance`),
        fetchPlayerTimePerformance: (targetPlayerId: string) => client.get(`/players/${targetPlayerId}/time-performance`),
        fetchPlayerGameLog: (targetPlayerId: string) => client.get(`/players/${targetPlayerId}/game-log`),
        fetchPlayerNemesis: (targetPlayerId: string) => client.get(`/players/${targetPlayerId}/nemesis`),
        sendHeartbeat: () => client.post(`/players/${playerId}/heartbeat`),
        fetchPlayerActivity: (playerIds: string[]) =>
            client.post('/players/activity', { player_ids: playerIds }),
    };
}
