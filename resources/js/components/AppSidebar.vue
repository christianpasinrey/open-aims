<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import * as LucideIcons from 'lucide-vue-next';
import {
    Inbox,
    LayoutGrid,
    CheckCircle2,
    CalendarRange,
    ChevronDown,
    ChevronRight,
    ChevronUp,
    FolderKanban,
    Users,
    Search,
    PenSquare,
    Settings,
    UserCircle,
    KeyRound,
    SunMoon,
    Github,
    Terminal,
    LogOut,
    Check,
    Plus,
    MoreHorizontal,
    Tag as TagIcon,
    Target,
    Trash2,
    Layers,
    Star,
} from 'lucide-vue-next';
import { resolveEmoji } from '@/lib/emoji';
import type { Favourite, FavouriteKind } from '@/composables/useFavourites';
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppLogo from '@/components/AppLogo.vue';

const WorkspaceJoinSearch = defineAsyncComponent(
    () => import('@/components/workspace/WorkspaceJoinSearch.vue'),
);
const InvitationsBell = defineAsyncComponent(
    () => import('@/components/InvitationsBell.vue'),
);
import Avatar from '@/components/repo/Avatar.vue';
import TeamIcon from '@/components/repo/TeamIcon.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';

type WorkspaceTeam = {
    id: number;
    name: string;
    key: string;
    icon?: string | null;
    color?: string | null;
    current_cycle_number?: number | null;
    upcoming_cycle_number?: number | null;
};
type WorkspaceProp = {
    id: number;
    name: string;
    slug: string;
    color?: string | null;
    logo_url?: string | null;
    teams: WorkspaceTeam[];
    favourites?: Favourite[];
};
type UserWorkspace = {
    id: number;
    name: string;
    slug: string;
    color?: string | null;
    logo_url?: string | null;
    role?: string | null;
};

const page = usePage();

type CurrentUser = { id: number; name: string; email: string };
const currentUser = computed<CurrentUser | null>(() => {
    const u = (page.props as { auth?: { user?: CurrentUser | null } }).auth
        ?.user;

    return u ?? null;
});

function signOut() {
    router.post('/logout', {}, { preserveScroll: false });
}

const workspace = computed<WorkspaceProp | null>(() => {
    const ws = (page.props as { workspace?: WorkspaceProp }).workspace;

    return ws ?? null;
});
const userWorkspaces = computed<UserWorkspace[]>(() => {
    const list = (page.props as { user_workspaces?: UserWorkspace[] })
        .user_workspaces;

    return Array.isArray(list) ? list : [];
});
const teams = computed<WorkspaceTeam[]>(() => workspace.value?.teams ?? []);
const favourites = computed<Favourite[]>(
    () => workspace.value?.favourites ?? [],
);

// Resolve a favourite's display icon: emoji shortcode → emoji, lucide name →
// component, falls back to Star.
const KIND_DEFAULT_ICON: Record<FavouriteKind, string> = {
    view: 'Eye',
    issue: 'Circle',
    project: 'FolderKanban',
    cycle: 'CalendarRange',
    inbox: 'Inbox',
    team_view: 'LayoutGrid',
    page: 'Star',
};

type IconMap = Record<string, unknown>;

function favouriteIconComponent(fav: Favourite): unknown | null {
    const raw = fav.icon ?? KIND_DEFAULT_ICON[fav.kind] ?? 'Star';
    const map = LucideIcons as unknown as IconMap;
    if (raw && Object.prototype.hasOwnProperty.call(map, raw)) {
        return map[raw];
    }
    return Star;
}

function favouriteEmoji(fav: Favourite): string | null {
    if (!fav.icon) {
        return null;
    }
    return resolveEmoji(fav.icon);
}

const currentUrl = computed<string>(() => {
    const url = (page as unknown as { url?: string }).url;

    return typeof url === 'string' ? url : '/';
});
const currentPath = computed<string>(
    () => currentUrl.value.split('?')[0] ?? '/',
);
const currentParams = computed<URLSearchParams>(() => {
    const url = currentUrl.value;
    const qIdx = url.indexOf('?');

    return new URLSearchParams(qIdx === -1 ? '' : url.slice(qIdx + 1));
});
const currentTeamParam = computed<string | null>(() => {
    const t = currentParams.value.get('team');

    return t ? t.toUpperCase() : null;
});
const currentAssigneeParam = computed<string | null>(() =>
    currentParams.value.get('assignee'),
);

const onIssuesIndex = computed(() => currentPath.value === '/issues');
const onProjectsIndex = computed(() => currentPath.value === '/projects');
const onCyclesIndex = computed(() => currentPath.value === '/cycles');
const onInitiativesIndex = computed(
    () =>
        currentPath.value === '/initiatives' ||
        currentPath.value.startsWith('/initiatives/'),
);
const onViewsIndex = computed(
    () =>
        currentPath.value === '/views' ||
        currentPath.value.startsWith('/views/'),
);
const isTrashActive = computed(() => currentPath.value === '/trash');
const isInboxActive = computed(() => currentPath.value === '/inbox');
const isMyIssuesActive = computed(
    () => onIssuesIndex.value && currentAssigneeParam.value === 'me',
);
const isProjectsActive = computed(
    () => onProjectsIndex.value && currentTeamParam.value === null,
);
const isInitiativesActive = computed(() => onInitiativesIndex.value);
const isViewsActive = computed(
    () => onViewsIndex.value && currentTeamParam.value === null,
);
const isTeamViewsActive = (key: string) =>
    onViewsIndex.value && currentTeamParam.value === key.toUpperCase();
const isTeamIssuesActive = (key: string) =>
    onIssuesIndex.value && currentTeamParam.value === key.toUpperCase();
const isTeamProjectsActive = (key: string) =>
    onProjectsIndex.value && currentTeamParam.value === key.toUpperCase();
const isTeamCyclesActive = (key: string) =>
    onCyclesIndex.value && currentTeamParam.value === key.toUpperCase();
const isTeamMembersActive = (key: string) =>
    currentPath.value.toUpperCase() === `/TEAMS/${key.toUpperCase()}/MEMBERS`;

// ----- Workspace section toggle (Initiatives / Projects / Views) -----
const workspaceOpen = ref(true);
// ----- Favourites section toggle -----
const favouritesOpen = ref(true);

// ----- Search dialog (Cmd+K / Ctrl+K) -----
const searchOpen = ref(false);
const searchQuery = ref('');
type SearchItem = {
    label: string;
    href: string;
    kind: string;
    color?: string | null;
};
const staticPages: SearchItem[] = [
    { label: 'Inbox', href: '/inbox', kind: 'Page' },
    { label: 'My issues', href: '/issues?assignee=me', kind: 'Page' },
    { label: 'Projects', href: '/projects', kind: 'Page' },
    { label: 'Cycles', href: '/cycles', kind: 'Page' },
    { label: 'Workspace settings', href: '/workspace/settings', kind: 'Page' },
    { label: 'Workspace members', href: '/workspace/members', kind: 'Page' },
    { label: 'Trash', href: '/trash', kind: 'Page' },
];
const searchResults = computed<SearchItem[]>(() => {
    const q = searchQuery.value.trim().toLowerCase();
    const teamItems: SearchItem[] = teams.value.map((t) => ({
        label: t.name,
        href: `/issues?team=${t.key}`,
        kind: 'Team',
        color: t.color ?? null,
    }));
    const pool = [...staticPages, ...teamItems];

    if (!q) {
        return pool.slice(0, 8);
    }

    return pool.filter((it) => it.label.toLowerCase().includes(q)).slice(0, 12);
});
function openSearch() {
    searchQuery.value = '';
    searchOpen.value = true;
}
function gotoSearchItem(item: SearchItem) {
    searchOpen.value = false;
    router.get(item.href);
}
function onKeydown(e: KeyboardEvent) {
    const meta = e.metaKey || e.ctrlKey;

    if (meta && e.key.toLowerCase() === 'k') {
        e.preventDefault();

        if (searchOpen.value) {
            searchOpen.value = false;
        } else {
            openSearch();
        }
    } else if (e.key === 'Escape' && searchOpen.value) {
        searchOpen.value = false;
    }
}
onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

// ----- New issue dialog -----
const newIssueOpen = ref(false);
const newIssueTitle = ref('');
const newIssueTeam = ref<string>('');
const newIssueSubmitting = ref(false);
const newIssueError = ref<string | null>(null);

watch(newIssueOpen, (open) => {
    if (open) {
        newIssueTitle.value = '';
        newIssueError.value = null;
        // Default to currently selected team in URL, or first team.
        const fromUrl = currentTeamParam.value;
        const fallback = teams.value[0]?.key ?? '';
        newIssueTeam.value = fromUrl ?? fallback;
    }
});

function submitNewIssue() {
    if (newIssueSubmitting.value) {
        return;
    }

    const title = newIssueTitle.value.trim();

    if (!title || !newIssueTeam.value) {
        newIssueError.value = 'Title and team are required.';

        return;
    }

    newIssueSubmitting.value = true;
    router.post(
        '/issues',
        { title, team_key: newIssueTeam.value },
        {
            preserveScroll: true,
            onFinish: () => {
                newIssueSubmitting.value = false;
            },
            onSuccess: () => {
                newIssueOpen.value = false;
            },
            onError: (errors) => {
                newIssueError.value =
                    Object.values(errors)[0] ?? 'Could not create issue.';
            },
        },
    );
}

// ----- Workspace switcher actions -----
function switchTo(slug: string) {
    if (workspace.value?.slug === slug) {
        return;
    }

    router.post(
        `/workspace/switch?workspace=${encodeURIComponent(slug)}`,
        {},
        { preserveScroll: false },
    );
}
function logout() {
    router.post('/logout');
}
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem class="flex items-center gap-1">
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <SidebarMenuButton
                                size="lg"
                                class="!h-auto !w-auto data-[state=open]:bg-sidebar-accent"
                            >
                                <AppLogo />
                                <ChevronDown
                                    class="size-3.5 text-muted-foreground"
                                />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            class="w-(--reka-dropdown-menu-trigger-width) min-w-64 rounded-lg"
                            align="start"
                            :side-offset="6"
                        >
                            <DropdownMenuItem as-child>
                                <Link
                                    :href="'/workspace/settings'"
                                    class="flex w-full cursor-pointer items-center justify-between"
                                >
                                    <span>Settings</span>
                                    <span
                                        class="font-mono text-[11px] text-muted-foreground"
                                        >G then S</span
                                    >
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link
                                    :href="'/workspace/members'"
                                    class="flex w-full cursor-pointer items-center"
                                >
                                    Invite and manage members
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuSub>
                                <DropdownMenuSubTrigger
                                    class="flex w-full items-center justify-between"
                                >
                                    <span class="flex items-center gap-2">
                                        <span>Switch workspace</span>
                                    </span>
                                    <span
                                        class="ml-auto pr-1 font-mono text-[11px] text-muted-foreground"
                                        >O then W</span
                                    >
                                </DropdownMenuSubTrigger>
                                <DropdownMenuSubContent
                                    class="min-w-64 rounded-lg"
                                >
                                    <DropdownMenuLabel
                                        class="truncate text-[12px] font-normal text-muted-foreground"
                                    >
                                        {{ currentUser?.email ?? '' }}
                                    </DropdownMenuLabel>
                                    <DropdownMenuItem
                                        v-for="ws in userWorkspaces"
                                        :key="ws.id"
                                        class="flex cursor-pointer items-center gap-2"
                                        @click="switchTo(ws.slug)"
                                    >
                                        <span
                                            v-if="!ws.logo_url"
                                            aria-hidden="true"
                                            class="flex size-5 shrink-0 items-center justify-center rounded-full text-[9.5px] font-semibold tracking-tight text-white uppercase"
                                            :style="{
                                                backgroundColor:
                                                    ws.color || '#6366f1',
                                            }"
                                        >
                                            {{
                                                ws.name
                                                    .replace(/\s+/g, '')
                                                    .slice(0, 2)
                                                    .toUpperCase()
                                            }}
                                        </span>
                                        <img
                                            v-else
                                            :src="ws.logo_url"
                                            :alt="ws.name"
                                            class="size-5 shrink-0 rounded-full object-cover"
                                        />
                                        <span
                                            class="min-w-0 flex-1 truncate text-[13px]"
                                            >{{ ws.name.toLowerCase() }}</span
                                        >
                                        <Check
                                            v-if="
                                                workspace &&
                                                ws.id === workspace.id
                                            "
                                            class="size-3.5 text-foreground"
                                        />
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuLabel
                                        class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                    >
                                        Account
                                    </DropdownMenuLabel>
                                    <DropdownMenuItem as-child>
                                        <Link
                                            href="/onboarding"
                                            class="flex w-full cursor-pointer items-center"
                                        >
                                            Create or join a workspace…
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        disabled
                                        class="cursor-pointer"
                                    >
                                        Add an account…
                                    </DropdownMenuItem>
                                </DropdownMenuSubContent>
                            </DropdownMenuSub>
                            <DropdownMenuItem
                                class="flex cursor-pointer items-center justify-between"
                                @click="logout"
                            >
                                <span>Log out</span>
                                <span
                                    class="font-mono text-[11px] text-muted-foreground"
                                    >Alt ⇧ Q</span
                                >
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <div class="ml-auto flex items-center gap-0.5">
                        <InvitationsBell />
                        <button
                            type="button"
                            class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-foreground"
                            aria-label="Search"
                            title="Search (Ctrl+K)"
                            @click="openSearch"
                        >
                            <Search class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-foreground"
                            aria-label="New issue"
                            title="New issue"
                            @click="newIssueOpen = true"
                        >
                            <PenSquare class="size-3.5" />
                        </button>
                    </div>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup class="px-2 py-0">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isInboxActive"
                            tooltip="Inbox"
                        >
                            <Link :href="'/inbox'">
                                <Inbox />
                                <span>Inbox</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isMyIssuesActive"
                            tooltip="My Issues"
                        >
                            <Link :href="'/issues?assignee=me'">
                                <CheckCircle2 />
                                <span>My issues</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <SidebarGroup class="px-2 py-0">
                <button
                    type="button"
                    class="flex w-full items-center gap-1 px-2 py-1.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase transition-colors hover:text-foreground"
                    @click="workspaceOpen = !workspaceOpen"
                >
                    <ChevronRight
                        class="size-3 transition-transform"
                        :class="{ 'rotate-90': workspaceOpen }"
                    />
                    Workspace
                </button>
                <SidebarMenu v-show="workspaceOpen">
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isInitiativesActive"
                            tooltip="Initiatives"
                        >
                            <Link :href="'/initiatives'">
                                <Target />
                                <span>Initiatives</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isProjectsActive"
                            tooltip="Projects"
                        >
                            <Link :href="'/projects'">
                                <FolderKanban />
                                <span>Projects</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isViewsActive"
                            tooltip="Views"
                        >
                            <Link :href="'/views'">
                                <Layers />
                                <span>Views</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="currentPath === '/workspace/teams'"
                            tooltip="Equipos"
                        >
                            <Link :href="'/workspace/teams'">
                                <Users />
                                <span>Equipos</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isTrashActive"
                            tooltip="Trash"
                        >
                            <Link :href="'/trash'">
                                <Trash2 />
                                <span>Trash</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <SidebarGroup v-if="favourites.length" class="px-2 py-0">
                <button
                    type="button"
                    class="flex w-full items-center gap-1 px-2 py-1.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase transition-colors hover:text-foreground"
                    @click="favouritesOpen = !favouritesOpen"
                >
                    <ChevronRight
                        class="size-3 transition-transform"
                        :class="{ 'rotate-90': favouritesOpen }"
                    />
                    Favourites
                </button>
                <SidebarMenu v-show="favouritesOpen">
                    <SidebarMenuItem v-for="fav in favourites" :key="fav.id">
                        <SidebarMenuButton as-child :tooltip="fav.label">
                            <Link :href="fav.href">
                                <span
                                    v-if="favouriteEmoji(fav)"
                                    class="text-[14px] leading-none"
                                    aria-hidden="true"
                                >
                                    {{ favouriteEmoji(fav) }}
                                </span>
                                <component
                                    v-else
                                    :is="favouriteIconComponent(fav)"
                                    :style="
                                        fav.color
                                            ? { color: fav.color }
                                            : undefined
                                    "
                                />
                                <span class="truncate">{{ fav.label }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <SidebarGroup v-if="teams.length" class="px-2 py-0">
                <SidebarGroupLabel>Your teams</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem
                        v-for="team in teams"
                        :key="team.id"
                        class="group/team"
                    >
                        <SidebarMenuButton
                            as-child
                            :tooltip="team.name"
                            :is-active="
                                isTeamIssuesActive(team.key) ||
                                isTeamProjectsActive(team.key) ||
                                isTeamCyclesActive(team.key) ||
                                isTeamMembersActive(team.key)
                            "
                        >
                            <Link :href="`/issues?team=${team.key}`">
                                <TeamIcon
                                    :icon="team.icon"
                                    :name="team.name"
                                    :color="team.color"
                                    :size="20"
                                />
                                <span class="truncate">{{ team.name }}</span>
                            </Link>
                        </SidebarMenuButton>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button
                                    type="button"
                                    class="absolute top-1.5 right-1.5 flex rounded p-0.5 text-muted-foreground opacity-0 transition-opacity group-hover/team:opacity-100 hover:bg-sidebar-accent hover:text-foreground focus-visible:opacity-100 data-[state=open]:opacity-100"
                                    aria-label="Team menu"
                                    title="Team menu"
                                >
                                    <MoreHorizontal class="size-3.5" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                side="right"
                                align="start"
                                :side-offset="4"
                                class="w-48"
                            >
                                <DropdownMenuItem as-child>
                                    <Link
                                        :href="`/teams/${team.key}/settings`"
                                        class="flex w-full cursor-pointer items-center"
                                    >
                                        <Settings class="mr-2 size-3.5" />
                                        Team settings
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem as-child>
                                    <Link
                                        :href="`/teams/${team.key}/members`"
                                        class="flex w-full cursor-pointer items-center"
                                    >
                                        <Users class="mr-2 size-3.5" />
                                        Members
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem as-child>
                                    <Link
                                        :href="`/teams/${team.key}/labels`"
                                        class="flex w-full cursor-pointer items-center"
                                    >
                                        <TagIcon class="mr-2 size-3.5" />
                                        Labels
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <SidebarMenuSub>
                            <SidebarMenuSubItem>
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="isTeamIssuesActive(team.key)"
                                >
                                    <Link :href="`/issues?team=${team.key}`">
                                        <LayoutGrid class="size-3.5" />
                                        <span>Issues</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                            <SidebarMenuSubItem>
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="isTeamCyclesActive(team.key)"
                                >
                                    <Link :href="`/cycles?team=${team.key}`">
                                        <CalendarRange class="size-3.5" />
                                        <span>Cycles</span>
                                    </Link>
                                </SidebarMenuSubButton>
                                <SidebarMenuSub>
                                    <SidebarMenuSubItem
                                        v-if="team.current_cycle_number"
                                    >
                                        <SidebarMenuSubButton as-child>
                                            <Link
                                                :href="`/cycles/${team.current_cycle_number}?team=${team.key}`"
                                            >
                                                <span>Current</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                    <SidebarMenuSubItem
                                        v-if="team.upcoming_cycle_number"
                                    >
                                        <SidebarMenuSubButton as-child>
                                            <Link
                                                :href="`/cycles/${team.upcoming_cycle_number}?team=${team.key}`"
                                            >
                                                <span>Upcoming</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                </SidebarMenuSub>
                            </SidebarMenuSubItem>
                            <SidebarMenuSubItem>
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="isTeamProjectsActive(team.key)"
                                >
                                    <Link :href="`/projects?team=${team.key}`">
                                        <FolderKanban class="size-3.5" />
                                        <span>Projects</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                            <SidebarMenuSubItem>
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="isTeamViewsActive(team.key)"
                                >
                                    <Link
                                        :href="`/views?scope=team&team=${team.key}`"
                                    >
                                        <Layers class="size-3.5" />
                                        <span>Views</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                            <SidebarMenuSubItem>
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="isTeamMembersActive(team.key)"
                                >
                                    <Link :href="`/teams/${team.key}/members`">
                                        <Users class="size-3.5" />
                                        <span>Members</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <!-- User menu — bottom of the sidebar -->
            <SidebarMenu v-if="currentUser">
                <SidebarMenuItem>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <SidebarMenuButton
                                size="lg"
                                tooltip="Account"
                                class="gap-2"
                            >
                                <Avatar
                                    :name="currentUser.name"
                                    :email="currentUser.email"
                                    :size="24"
                                />
                                <span
                                    class="grid flex-1 text-left leading-tight group-data-[collapsible=icon]:hidden"
                                >
                                    <span
                                        class="truncate text-[13px] font-medium"
                                        >{{ currentUser.name }}</span
                                    >
                                    <span
                                        class="truncate text-[11px] text-muted-foreground"
                                        >{{ currentUser.email }}</span
                                    >
                                </span>
                                <ChevronUp
                                    class="ml-auto size-3.5 text-muted-foreground group-data-[collapsible=icon]:hidden"
                                />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            side="top"
                            align="end"
                            class="w-60"
                        >
                            <div class="flex items-center gap-2 px-2 py-1.5">
                                <Avatar
                                    :name="currentUser.name"
                                    :email="currentUser.email"
                                    :size="28"
                                />
                                <div class="min-w-0">
                                    <div
                                        class="truncate text-[13px] font-medium"
                                    >
                                        {{ currentUser.name }}
                                    </div>
                                    <div
                                        class="truncate text-[11.5px] text-muted-foreground"
                                    >
                                        {{ currentUser.email }}
                                    </div>
                                </div>
                            </div>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem as-child>
                                <Link :href="'/settings/profile'">
                                    <UserCircle class="size-3.5" />
                                    <span>Profile</span>
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link :href="'/settings/security'">
                                    <KeyRound class="size-3.5" />
                                    <span>Security & password</span>
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link :href="'/settings/appearance'">
                                    <SunMoon class="size-3.5" />
                                    <span>Appearance</span>
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem as-child>
                                <Link :href="'/workspace/settings'">
                                    <Settings class="size-3.5" />
                                    <span>Workspace settings</span>
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link :href="'/settings/github'">
                                    <Github class="size-3.5" />
                                    <span>GitHub integration</span>
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link :href="'/settings/developer'">
                                    <Terminal class="size-3.5" />
                                    <span>Connect Claude (MCP)</span>
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                @select="signOut"
                            >
                                <LogOut class="size-3.5" />
                                <span>Sign out</span>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarFooter>

        <!-- Search dialog -->
        <Dialog v-model:open="searchOpen">
            <DialogContent class="overflow-hidden p-0 sm:max-w-xl">
                <DialogHeader class="sr-only">
                    <DialogTitle>Search</DialogTitle>
                    <DialogDescription>
                        Find a team or jump to a page.
                    </DialogDescription>
                </DialogHeader>
                <div
                    class="flex items-center gap-2 border-b border-border px-3 py-2.5"
                >
                    <Search class="size-4 text-muted-foreground" />
                    <input
                        v-model="searchQuery"
                        autofocus
                        type="text"
                        placeholder="Search pages, teams…"
                        class="flex-1 bg-transparent text-[13.5px] outline-none placeholder:text-muted-foreground"
                        @keydown.enter.prevent="
                            searchResults[0] && gotoSearchItem(searchResults[0])
                        "
                    />
                    <kbd
                        class="rounded border border-border px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                    >
                        Esc
                    </kbd>
                </div>
                <ul
                    v-if="searchResults.length"
                    class="max-h-80 overflow-y-auto py-1"
                >
                    <li
                        v-for="(item, idx) in searchResults"
                        :key="`${item.kind}-${idx}-${item.label}`"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-[13px] hover:bg-accent/60"
                            @click="gotoSearchItem(item)"
                        >
                            <span
                                v-if="item.kind === 'Team'"
                                class="flex size-4 shrink-0 items-center justify-center rounded text-[9px] font-semibold text-white uppercase"
                                :style="{
                                    backgroundColor: item.color || '#6366f1',
                                }"
                                >{{ item.label.charAt(0) }}</span
                            >
                            <Search
                                v-else
                                class="size-3.5 shrink-0 text-muted-foreground"
                            />
                            <span class="flex-1 truncate">{{
                                item.label
                            }}</span>
                            <span
                                class="text-[11px] tracking-wide text-muted-foreground uppercase"
                                >{{ item.kind }}</span
                            >
                        </button>
                    </li>
                </ul>
                <div
                    v-else
                    class="px-4 py-6 text-center text-[12.5px] text-muted-foreground"
                >
                    No matches.
                </div>

                <!-- Workspace search group (only when user has typed a query) -->
                <template v-if="searchQuery.trim()">
                    <div class="border-t border-border px-3 pb-1 pt-2.5 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                        Workspaces
                    </div>
                    <div class="px-3 pb-3">
                        <WorkspaceJoinSearch @joined="searchOpen = false" />
                    </div>
                </template>
            </DialogContent>
        </Dialog>

        <!-- New issue dialog -->
        <Dialog v-model:open="newIssueOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>New issue</DialogTitle>
                    <DialogDescription>
                        Quickly create an issue. You can flesh it out from the
                        issue page.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitNewIssue">
                    <div class="grid gap-2">
                        <Label for="ni-title">Title</Label>
                        <Input
                            id="ni-title"
                            v-model="newIssueTitle"
                            placeholder="What needs to be done?"
                            autofocus
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="ni-team">Team</Label>
                        <select
                            id="ni-team"
                            v-model="newIssueTeam"
                            required
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-[13px] outline-none focus-visible:border-ring"
                        >
                            <option
                                v-for="t in teams"
                                :key="t.id"
                                :value="t.key"
                            >
                                {{ t.name }} ({{ t.key }})
                            </option>
                        </select>
                    </div>
                    <p
                        v-if="newIssueError"
                        class="text-[12.5px] text-destructive"
                    >
                        {{ newIssueError }}
                    </p>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            @click="newIssueOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="newIssueSubmitting">
                            <Plus class="mr-1 size-3.5" />
                            Create issue
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

    </Sidebar>
    <slot />
</template>
