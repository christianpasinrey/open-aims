<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Inbox,
    LayoutGrid,
    CheckCircle2,
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
import { useCurrentUrl } from '@/composables/useCurrentUrl';

type WorkspaceTeam = {
    id: number;
    name: string;
    key: string;
    icon?: string | null;
    color?: string | null;
};
type WorkspaceProp = { id: number; name: string; slug: string; teams: WorkspaceTeam[] };

const page = usePage();
const { isCurrentUrl } = useCurrentUrl();

const workspace = computed<WorkspaceProp | null>(() => {
    const ws = (page.props as { workspace?: WorkspaceProp }).workspace;
    return ws ?? null;
});
const teams = computed<WorkspaceTeam[]>(() => workspace.value?.teams ?? []);

const teamHref = (key: string) => `/issues?team=${encodeURIComponent(key)}`;

const currentUrl = computed<string>(() => {
    const url = (page as unknown as { url?: string }).url;
    return typeof url === 'string' ? url : '/';
});
const currentTeamParam = computed<string | null>(() => {
    const url = currentUrl.value;
    const qIdx = url.indexOf('?');
    if (qIdx === -1) return null;
    const params = new URLSearchParams(url.slice(qIdx + 1));
    const team = params.get('team');
    return team ? team.toUpperCase() : null;
});
const onIssuesIndex = computed<boolean>(() => {
    const url = currentUrl.value;
    const path = url.split('?')[0];
    return path === '/issues';
});
const isMyIssuesActive = computed<boolean>(() => onIssuesIndex.value && currentTeamParam.value === null);
const isTeamActive = (key: string) =>
    onIssuesIndex.value && currentTeamParam.value === key.toUpperCase();
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
                            :is-active="isCurrentUrl('/inbox')"
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
                            <Link :href="'/issues'">
                                <CheckCircle2 />
                                <span>My Issues</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl('/projects')"
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
                            <Link :href="teamHref(team.key)">
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
                                    <Link :href="teamHref(team.key)">
                                        <LayoutGrid class="size-3.5" />
                                        <span>Issues</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                            <SidebarMenuSubItem>
                                <SidebarMenuSubButton as-child>
                                    <Link
                                        :href="`/projects?team=${team.key}`"
                                    >
                                        <FolderKanban class="size-3.5" />
                                        <span>Projects</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                            <SidebarMenuSubItem>
                                <SidebarMenuSubButton as-child>
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
                        :is-active="isCurrentUrl('/settings/profile')"
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
