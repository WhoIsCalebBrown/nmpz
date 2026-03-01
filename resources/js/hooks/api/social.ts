import type { AxiosInstance } from 'axios';

export function socialApi(client: AxiosInstance, playerId: string) {
    return {
        fetchPlayerProfile: (targetPlayerId: string) => client.get(`/players/${targetPlayerId}/profile`),
        fetchPlayerMilestones: (targetPlayerId: string) => client.get(`/players/${targetPlayerId}/milestones`),
        fetchPlayerActivityFeed: (targetPlayerId: string) => client.get(`/players/${targetPlayerId}/activity-feed`),
        fetchPlayerStreaks: (targetPlayerId: string) => client.get(`/players/${targetPlayerId}/streaks`),
        fetchPlayerEloHistory: (targetPlayerId: string, limit?: number) =>
            client.get(`/players/${targetPlayerId}/elo-history${limit ? `?limit=${limit}` : ''}`),
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
        compareWithPlayer: (opponentId: string) => client.get(`/players/${playerId}/compare/${opponentId}`),
        sendHeartbeat: () => client.post(`/players/${playerId}/heartbeat`),
        fetchPlayerActivity: (playerIds: string[]) =>
            client.post('/players/activity', { player_ids: playerIds }),
    };
}
