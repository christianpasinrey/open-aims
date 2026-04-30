<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Inbox,
    LayoutGrid,
    CheckCircle2,
    CalendarRange,
    FolderKanban,
    Settings,
    Users,
} from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import NavUser from '@/components/NavUser.vue';
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
};
type WorkspaceProp = { id: number; name: string; slug: string; teams: WorkspaceTeam[] };

const page = usePage();

const workspace = computed<WorkspaceProp | null>(() => {
    const ws = (page.props as { workspace?: WorkspaceProp }).workspace;
    return ws ?? null;
});
const teams = computed<WorkspaceTeam[]>(() => workspace.value?.teams ?? []);

const currentUrl = computed<string>(() => {
    const url = (page as unknown as { url?: string }).url;
    return typeof url === 'string' ? url : '/';
});
const currentPath = computed<string>(() => currentUrl.value.split('?')[0] ?? '/');
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

const onIssuesIndex = computed<boolean>(() => currentPath.value === '/issues');
const isInboxActive = computed<boolean>(() => currentPath.value === '/inbox');
const isMyIssuesActive = computed<boolean>(
    () => onIssuesIndex.value && currentAssigneeParam.value === 'me',
);
const isProjectsActive = computed<boolean>(() =>
    currentPath.value.startsWith('/projects'),
);
const isTeamActive = (key: string) =>
    onIssuesIndex.value && currentTeamParam.value === key.toUpperCase();
const isTeamCyclesActive = (key: string) =>
    currentPath.value === '/cycles' && currentTeamParam.value === key.toUpperCase();
const isTeamMembersActive = (key: string) =>
    currentPath.value === `/teams/${key.toUpperCase()}/members` ||
    currentPath.value === `/teams/${key.toLowerCase()}/members`;
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="'/issues'">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
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
                                <span>My Issues</span>
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
                </SidebarMenu>
            </SidebarGroup>

            <SidebarGroup v-if="teams.length" class="px-2 py-0">
                <SidebarGroupLabel>Your teams</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="team in teams" :key="team.id">
                        <SidebarMenuButton
                            as-child
                            :is-active="isTeamActive(team.key)"
                            :tooltip="team.name"
                        >
                            <Link :href="`/issues?team=${team.key}`">
                                <span
                                    aria-hidden="true"
                                    class="flex size-5 items-center justify-center rounded-[5px] text-[10px] font-semibold uppercase tracking-tight text-white"
                                    :style="{
                                        backgroundColor:
                                            team.color || '#6366f1',
                                    }"
                                >
                                    {{ team.key.charAt(0) }}
                                </span>
                                <span class="truncate">{{ team.name }}</span>
                            </Link>
                        </SidebarMenuButton>
                        <SidebarMenuSub>
                            <SidebarMenuSubItem>
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="isTeamActive(team.key)"
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
                            </SidebarMenuSubItem>
                            <SidebarMenuSubItem>
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="isTeamMembersActive(team.key)"
                                >
                                    <Link
                                        :href="`/teams/${team.key}/members`"
                                    >
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
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        as-child
                        :is-active="currentPath.startsWith('/settings')"
                        tooltip="Settings"
                    >
                        <Link :href="'/settings/profile'">
                            <Settings />
                            <span>Settings</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
