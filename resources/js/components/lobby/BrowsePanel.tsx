import React, { useEffect, useRef, useState } from 'react';
import AchievementsPanel from '@/components/welcome/AchievementsPanel';
import ActivePlayersPanel from '@/components/welcome/ActivePlayersPanel';
import CommunityHighlightsPanel from '@/components/welcome/CommunityHighlightsPanel';
import FavoriteMapsPanel from '@/components/welcome/FavoriteMapsPanel';
import FriendsPanel from '@/components/welcome/FriendsPanel';
import GameHistoryPanel from '@/components/welcome/GameHistoryPanel';
import GlobalRecordsPanel from '@/components/welcome/GlobalRecordsPanel';
import GlobalStatsPanel from '@/components/welcome/GlobalStatsPanel';
import Leaderboard from '@/components/welcome/Leaderboard';
import LeaderboardMoversPanel from '@/components/welcome/LeaderboardMoversPanel';
import LiveGamesList from '@/components/welcome/LiveGamesList';
import MapDifficultyPanel from '@/components/welcome/MapDifficultyPanel';
import MatchmakingStatsPanel from '@/components/welcome/MatchmakingStatsPanel';
import FormatStatsPanel from '@/components/welcome/FormatStatsPanel';
import PlayerMilestonesPanel from '@/components/welcome/PlayerMilestonesPanel';
import PlayerStreaksPanel from '@/components/welcome/PlayerStreaksPanel';
import PlayerRivalsPanel from '@/components/welcome/PlayerRivalsPanel';
import PlayerStatsPanel from '@/components/welcome/PlayerStatsPanel';
import SeasonPanel from '@/components/welcome/SeasonPanel';
import SoloLeaderboardPanel from '@/components/welcome/SoloLeaderboardPanel';
import { Eye, User, Users } from 'lucide-react';

/*
 * Two-tier navigation: top-level groups → primary tabs → optional sub-tabs.
 *
 * Profile ─ overview | history | progress ▸ (achievements·milestones·streaks·season)
 *                                | play     ▸ (rivals·formats·maps·solo)
 *
 * Community ─ rankings ▸ (leaderboard·active·movers)
 *           | social   ▸ (friends·highlights)
 *           | explore  ▸ (global·records·maps·mmr)
 *
 * Watch ─ (no sub-tabs)
 */

type ActiveGroup = 'none' | 'profile' | 'community' | 'watch';

// -- Profile -----------------------------------------------------------
type ProfilePrimary = 'overview' | 'history' | 'progress' | 'play';
type ProgressSub = 'achievements' | 'milestones' | 'streaks' | 'season';
type PlaySub = 'rivals' | 'formats' | 'maps' | 'solo';

// -- Community ---------------------------------------------------------
type CommunityPrimary = 'rankings' | 'social' | 'explore';
type RankingsSub = 'leaderboard' | 'active' | 'movers';
type SocialSub = 'friends' | 'highlights';
type ExploreSub = 'global' | 'records' | 'maps' | 'mmr';

const PROFILE_PRIMARY: { key: ProfilePrimary; label: string }[] = [
    { key: 'overview', label: 'overview' },
    { key: 'history', label: 'history' },
    { key: 'progress', label: 'progress' },
    { key: 'play', label: 'play' },
];
const PROGRESS_SUBS: { key: ProgressSub; label: string }[] = [
    { key: 'achievements', label: 'achievements' },
    { key: 'milestones', label: 'milestones' },
    { key: 'streaks', label: 'streaks' },
    { key: 'season', label: 'season' },
];
const PLAY_SUBS: { key: PlaySub; label: string }[] = [
    { key: 'rivals', label: 'rivals' },
    { key: 'formats', label: 'formats' },
    { key: 'maps', label: 'maps' },
    { key: 'solo', label: 'solo' },
];

const COMMUNITY_PRIMARY: { key: CommunityPrimary; label: string }[] = [
    { key: 'rankings', label: 'rankings' },
    { key: 'social', label: 'social' },
    { key: 'explore', label: 'explore' },
];
const RANKINGS_SUBS: { key: RankingsSub; label: string }[] = [
    { key: 'leaderboard', label: 'leaderboard' },
    { key: 'active', label: 'active' },
    { key: 'movers', label: 'movers' },
];
const SOCIAL_SUBS: { key: SocialSub; label: string }[] = [
    { key: 'friends', label: 'friends' },
    { key: 'highlights', label: 'highlights' },
];
const EXPLORE_SUBS: { key: ExploreSub; label: string }[] = [
    { key: 'global', label: 'global' },
    { key: 'records', label: 'records' },
    { key: 'maps', label: 'maps' },
    { key: 'mmr', label: 'mmr' },
];

const GROUP_BUTTONS: { key: ActiveGroup; label: string; icon: React.ReactNode }[] = [
    { key: 'profile', label: 'Profile', icon: <User size={14} /> },
    { key: 'community', label: 'Community', icon: <Users size={14} /> },
    { key: 'watch', label: 'Watch', icon: <Eye size={14} /> },
];

function Divider({ label }: { label: string }) {
    return (
        <div className="flex items-center gap-2">
            <div className="flex-1 border-t border-white/10" />
            <span className="shrink-0 text-[10px] uppercase tracking-widest text-white/25">{label}</span>
            <div className="flex-1 border-t border-white/10" />
        </div>
    );
}

function TabRow<T extends string>({
    tabs,
    active,
    onSelect,
    size = 'sm',
}: {
    tabs: { key: T; label: string }[];
    active: T;
    onSelect: (k: T) => void;
    size?: 'sm' | 'xs';
}) {
    const textSize = size === 'xs' ? 'text-[9px]' : 'text-[10px]';
    const px = size === 'xs' ? 'px-2' : 'px-3';
    return (
        <div className="flex justify-center gap-0 border-b border-white/10">
            {tabs.map(({ key, label }) => (
                <button
                    key={key}
                    type="button"
                    onClick={() => onSelect(key)}
                    className={`${px} py-1 ${textSize} transition ${
                        active === key
                            ? 'border-b border-white/40 text-white'
                            : 'text-white/30 hover:text-white/50'
                    }`}
                >
                    {label}
                </button>
            ))}
        </div>
    );
}

export default function BrowsePanel({
    playerId,
    onViewDetail,
    onViewReplay,
    onViewProfile,
}: {
    playerId: string;
    onViewDetail: (id: string) => void;
    onViewReplay: (id: string) => void;
    onViewProfile: (id: string) => void;
}) {
    const [activeGroup, setActiveGroup] = useState<ActiveGroup>('none');

    // Profile state
    const [profilePrimary, setProfilePrimary] = useState<ProfilePrimary>('overview');
    const [progressSub, setProgressSub] = useState<ProgressSub>('achievements');
    const [playSub, setPlaySub] = useState<PlaySub>('rivals');

    // Community state
    const [communityPrimary, setCommunityPrimary] = useState<CommunityPrimary>('rankings');
    const [rankingsSub, setRankingsSub] = useState<RankingsSub>('leaderboard');
    const [socialSub, setSocialSub] = useState<SocialSub>('friends');
    const [exploreSub, setExploreSub] = useState<ExploreSub>('global');

    // Panel transition machinery
    const [mountedPanels, setMountedPanels] = useState<Set<string>>(new Set());
    const [visibleKey, setVisibleKey] = useState<string | null>(null);
    const panelWrapperRef = useRef<HTMLDivElement>(null);
    const fadeRef = useRef<ReturnType<typeof setTimeout>>(undefined);

    function activePanelKey(): string | null {
        if (activeGroup === 'profile') {
            if (profilePrimary === 'overview') return 'p-overview';
            if (profilePrimary === 'history') return 'p-history';
            if (profilePrimary === 'progress') return `p-progress-${progressSub}`;
            if (profilePrimary === 'play') return `p-play-${playSub}`;
        }
        if (activeGroup === 'community') {
            if (communityPrimary === 'rankings') return `c-rankings-${rankingsSub}`;
            if (communityPrimary === 'social') return `c-social-${socialSub}`;
            if (communityPrimary === 'explore') return `c-explore-${exploreSub}`;
        }
        if (activeGroup === 'watch') return 'watch';
        return null;
    }

    const targetKey = activePanelKey();

    useEffect(() => {
        if (targetKey && !mountedPanels.has(targetKey)) {
            setMountedPanels((prev) => new Set(prev).add(targetKey));
        }
    }, [targetKey]);

    useEffect(() => {
        if (targetKey === visibleKey) return;
        clearTimeout(fadeRef.current);

        const wrapper = panelWrapperRef.current;

        if (!visibleKey || !wrapper) {
            setVisibleKey(targetKey);
            if (wrapper) {
                wrapper.style.opacity = '0';
                requestAnimationFrame(() => {
                    wrapper.style.transition = 'opacity 250ms ease';
                    wrapper.style.opacity = '1';
                });
            }
            return;
        }

        wrapper.style.transition = 'opacity 200ms ease';
        wrapper.style.opacity = '0';

        fadeRef.current = setTimeout(() => {
            setVisibleKey(targetKey);
            requestAnimationFrame(() => {
                wrapper.style.opacity = '1';
            });
        }, 200);

        return () => clearTimeout(fadeRef.current);
    }, [targetKey]);

    const panelComponents: Record<string, React.ReactNode> = {
        // Profile
        'p-overview': <PlayerStatsPanel playerId={playerId} />,
        'p-history': (
            <GameHistoryPanel
                playerId={playerId}
                onViewDetail={onViewDetail}
                onViewReplay={onViewReplay}
            />
        ),
        'p-progress-achievements': <AchievementsPanel playerId={playerId} />,
        'p-progress-milestones': <PlayerMilestonesPanel playerId={playerId} />,
        'p-progress-streaks': <PlayerStreaksPanel playerId={playerId} />,
        'p-progress-season': <SeasonPanel playerId={playerId} />,
        'p-play-rivals': <PlayerRivalsPanel playerId={playerId} onViewProfile={onViewProfile} />,
        'p-play-formats': <FormatStatsPanel playerId={playerId} />,
        'p-play-maps': <FavoriteMapsPanel playerId={playerId} />,
        'p-play-solo': <SoloLeaderboardPanel playerId={playerId} />,

        // Community
        'c-rankings-leaderboard': <Leaderboard playerId={playerId} />,
        'c-rankings-active': <ActivePlayersPanel playerId={playerId} onViewProfile={onViewProfile} />,
        'c-rankings-movers': <LeaderboardMoversPanel playerId={playerId} onViewProfile={onViewProfile} />,
        'c-social-friends': (
            <FriendsPanel
                playerId={playerId}
                onViewProfile={onViewProfile}
            />
        ),
        'c-social-highlights': <CommunityHighlightsPanel playerId={playerId} />,
        'c-explore-global': <GlobalStatsPanel playerId={playerId} />,
        'c-explore-records': <GlobalRecordsPanel playerId={playerId} onViewProfile={onViewProfile} />,
        'c-explore-maps': <MapDifficultyPanel playerId={playerId} />,
        'c-explore-mmr': <MatchmakingStatsPanel playerId={playerId} />,

        // Watch
        'watch': <LiveGamesList playerId={playerId} />,
    };

    // -- Sub-tab row for current selection ----------------------------------
    function renderSubTabs() {
        if (activeGroup === 'profile') {
            if (profilePrimary === 'progress')
                return <TabRow tabs={PROGRESS_SUBS} active={progressSub} onSelect={setProgressSub} size="xs" />;
            if (profilePrimary === 'play')
                return <TabRow tabs={PLAY_SUBS} active={playSub} onSelect={setPlaySub} size="xs" />;
        }
        if (activeGroup === 'community') {
            if (communityPrimary === 'rankings')
                return <TabRow tabs={RANKINGS_SUBS} active={rankingsSub} onSelect={setRankingsSub} size="xs" />;
            if (communityPrimary === 'social')
                return <TabRow tabs={SOCIAL_SUBS} active={socialSub} onSelect={setSocialSub} size="xs" />;
            if (communityPrimary === 'explore')
                return <TabRow tabs={EXPLORE_SUBS} active={exploreSub} onSelect={setExploreSub} size="xs" />;
        }
        return null;
    }

    return (
        <>
            <div className="px-4">
                <Divider label="browse" />
            </div>
            <div className="px-4 py-3">
                {/* Top-level group buttons */}
                <div className="flex justify-center gap-1">
                    {GROUP_BUTTONS.map(({ key, label, icon }) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => setActiveGroup(activeGroup === key ? 'none' : key)}
                            className={`flex items-center gap-1.5 border px-3 py-1.5 text-xs transition ${
                                activeGroup === key
                                    ? 'border-white/25 bg-white/10 text-white'
                                    : 'border-white/10 text-white/35 hover:bg-white/5 hover:text-white/50'
                            }`}
                        >
                            {icon}
                            {label}
                        </button>
                    ))}
                </div>

                {/* Primary tab row */}
                {activeGroup === 'profile' && (
                    <div className="mt-2">
                        <TabRow tabs={PROFILE_PRIMARY} active={profilePrimary} onSelect={setProfilePrimary} />
                    </div>
                )}
                {activeGroup === 'community' && (
                    <div className="mt-2">
                        <TabRow tabs={COMMUNITY_PRIMARY} active={communityPrimary} onSelect={setCommunityPrimary} />
                    </div>
                )}

                {/* Sub-tab row (only when a primary tab has children) */}
                {activeGroup !== 'none' && renderSubTabs() && (
                    <div className="mt-1">{renderSubTabs()}</div>
                )}

                {/* Panel content */}
                {activeGroup !== 'none' && (
                    <div ref={panelWrapperRef} className="mt-3">
                        {Array.from(mountedPanels).map((key) => (
                            <div
                                key={key}
                                style={{ display: key === visibleKey ? 'block' : 'none' }}
                            >
                                {panelComponents[key]}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
