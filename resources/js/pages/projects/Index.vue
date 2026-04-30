<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';
import {
    Calendar,
    Check,
    ChevronDown,
    ChevronRight,
    LayoutGrid,
    Plus,
    SlidersHorizontal,
    Star,
} from 'lucide-vue-next';
import Avatar from '@/components/repo/Avatar.vue';
import ProjectIcon from '@/components/repo/ProjectIcon.vue';
import StatusIcon from '@/components/repo/StatusIcon.vue';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

type Member = { id: number; name: string };
type Project = {
    id: number;
    name: string;
    slug: string;
    state: string | null;
    color: string | null;
    icon: string | null;
    description: string | null;
    start_date: string | null;
    target_date: string | null;
    completed_at: string | null;
    lead: { id: number; name: string; email: string } | null;
    members: Member[];
    total_issues: number;
    completed_issues: number;
    progress: number;
};
type WorkspaceMember = { id: number; name: string; email: string };
type WorkspaceTeam = {
    id: number;
    name: string;
    key: string;
    color: string | null;
};
type Filters = {
    team: string | null;
    status: string | null;
    lead: number | null;
    group: 'none' | 'status' | 'lead';
    sort: 'status' | 'name' | 'target' | 'issues';
};

const props = defineProps<{
    projects: Project[];
    states: Record<string, string>;
    team: { id: number; name: string; key: string; color: string | null } | null;
    members: WorkspaceMember[];
    filters: Filters;
}>();

const page = usePage<{
    workspace: { teams: WorkspaceTeam[] } | null;
}>();

const workspaceTeams = computed<WorkspaceTeam[]>(
    () => page.props.workspace?.teams ?? [],
);

const STATE_LABELS: Record<string, string> = {
    backlog: 'Backlog',
    planned: 'Planned',
    started: 'In progress',
    paused: 'Paused',
    completed: 'Completed',
    canceled: 'Canceled',
};
const STATE_ORDER = ['backlog', 'planned', 'started', 'paused', 'completed', 'canceled'];

function projectStatusType(
    state: string | null,
): 'backlog' | 'started' | 'unstarted' | 'completed' | 'canceled' {
    switch (state) {
        case 'started':
            return 'started';
        case 'paused':
            return 'unstarted';
        case 'completed':
            return 'completed';
        case 'canceled':
            return 'canceled';
        default:
            return 'backlog';
    }
}

const headerTitle = computed<string>(() => {
    if (props.team) return `${props.team.name} · Projects`;
    if (favouritesView.value) return 'Favourite projects';
    return 'Projects';
});

function fmtDate(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

function isOverdue(target: string | null, completedAt: string | null) {
    if (!target || completedAt) return false;
    return new Date(target).getTime() < Date.now();
}

const ringR = 5;
const ringC = 2 * Math.PI * ringR;
function ringDashOffset(percent: number) {
    return ringC * (1 - Math.max(0, Math.min(100, percent)) / 100);
}
function ringStroke(percent: number, state: string | null) {
    if (state === 'canceled') return '#a1a1aa';
    if (state === 'completed' || percent >= 100) return '#10b981';
    if (percent > 0) return '#f59e0b';
    return '#a1a1aa';
}

// ---- URL filter helpers ----
type ParamPatch = {
    team?: string | null;
    status?: string | null;
    lead?: number | null;
    group?: string | null;
    sort?: string | null;
    fav?: '1' | null;
};

function applyParams(patch: ParamPatch) {
    const current: Record<string, string | null | number | undefined> = {
        team: props.filters.team ?? props.team?.key ?? null,
        status: props.filters.status ?? null,
        lead: props.filters.lead ?? null,
        group: props.filters.group !== 'none' ? props.filters.group : null,
        sort: props.filters.sort !== 'status' ? props.filters.sort : null,
        fav: favouritesView.value ? '1' : null,
        ...patch,
    };
    const merged: Record<string, string> = {};
    for (const [k, v] of Object.entries(current)) {
        if (v === null || v === undefined || v === '') continue;
        merged[k] = String(v);
    }
    router.get('/projects', merged, { preserveState: false, replace: false });
}

function setStatus(value: string | null) {
    applyParams({ status: value });
}
function setLead(value: number | null) {
    applyParams({ lead: value });
}
function setGroup(value: Filters['group']) {
    applyParams({ group: value === 'none' ? null : value });
}
function setSort(value: Filters['sort']) {
    applyParams({ sort: value === 'status' ? null : value });
}
function clearFilters() {
    applyParams({ status: null, lead: null });
}

// ---- Favourites (localStorage) ----
const FAV_PREFIX = 'aims:favourites:project:';
const favourites = ref<Set<string>>(new Set());
const favouritesView = ref<boolean>(false);

function readFavourites(): Set<string> {
    if (typeof window === 'undefined') return new Set();
    const out = new Set<string>();
    try {
        for (let i = 0; i < window.localStorage.length; i++) {
            const key = window.localStorage.key(i);
            if (!key || !key.startsWith(FAV_PREFIX)) continue;
            if (window.localStorage.getItem(key) === '1') {
                out.add(key.slice(FAV_PREFIX.length));
            }
        }
    } catch {
        // localStorage may be unavailable in private mode
    }
    return out;
}

function isFav(slug: string): boolean {
    return favourites.value.has(slug);
}

function toggleFav(slug: string, event?: Event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    const key = FAV_PREFIX + slug;
    const next = new Set(favourites.value);
    if (next.has(slug)) {
        next.delete(slug);
        try { window.localStorage.removeItem(key); } catch { /* ignore */ }
    } else {
        next.add(slug);
        try { window.localStorage.setItem(key, '1'); } catch { /* ignore */ }
    }
    favourites.value = next;
}

onMounted(() => {
    favourites.value = readFavourites();
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        favouritesView.value = url.searchParams.get('fav') === '1';
    }
});

// Re-read favourites on Inertia navigations (e.g. user comes back from a project)
watch(
    () => props.projects.map((p) => p.slug).join(','),
    () => {
        favourites.value = readFavourites();
    },
);

const visibleProjects = computed<Project[]>(() => {
    if (!favouritesView.value) return props.projects;
    return props.projects.filter((p) => favourites.value.has(p.slug));
});

// Grouping for the list. The backend already orders the rows; we just bucket.
type Group = { key: string; label: string; projects: Project[] };
const grouped = computed<Group[]>(() => {
    const list = visibleProjects.value;
    if (props.filters.group === 'none') {
        return [{ key: 'all', label: '', projects: list }];
    }
    if (props.filters.group === 'status') {
        const buckets = new Map<string, Project[]>();
        for (const p of list) {
            const key = p.state ?? 'backlog';
            if (!buckets.has(key)) buckets.set(key, []);
            buckets.get(key)!.push(p);
        }
        const out: Group[] = [];
        for (const key of STATE_ORDER) {
            const arr = buckets.get(key);
            if (arr && arr.length) {
                out.push({ key, label: STATE_LABELS[key] ?? key, projects: arr });
                buckets.delete(key);
            }
        }
        for (const [key, arr] of buckets.entries()) {
            out.push({ key, label: STATE_LABELS[key] ?? key, projects: arr });
        }
        return out;
    }
    // group by lead
    const buckets = new Map<string, Project[]>();
    for (const p of list) {
        const key = p.lead ? `u-${p.lead.id}` : 'no-lead';
        if (!buckets.has(key)) buckets.set(key, []);
        buckets.get(key)!.push(p);
    }
    return Array.from(buckets.entries()).map(([key, arr]) => ({
        key,
        label:
            key === 'no-lead'
                ? 'No lead'
                : (arr[0]?.lead?.name ?? 'Unknown'),
        projects: arr,
    }));
});

const collapsedGroups = ref<Set<string>>(new Set());
function toggleGroup(key: string) {
    const n = new Set(collapsedGroups.value);
    if (n.has(key)) n.delete(key);
    else n.add(key);
    collapsedGroups.value = n;
}

// ---- New project dialog ----
const newDialogOpen = ref<boolean>(false);
const newForm = ref<{
    name: string;
    description: string;
    team_keys: string[];
    lead_user_id: number | null;
    state: string;
    start_date: string;
    target_date: string;
}>({
    name: '',
    description: '',
    team_keys: [],
    lead_user_id: null,
    state: 'backlog',
    start_date: '',
    target_date: '',
});
const newSubmitting = ref<boolean>(false);
const newError = ref<string | null>(null);

function openNew() {
    newForm.value = {
        name: '',
        description: '',
        team_keys: props.team ? [props.team.key] : [],
        lead_user_id: null,
        state: 'backlog',
        start_date: '',
        target_date: '',
    };
    newError.value = null;
    newDialogOpen.value = true;
}

function toggleNewTeam(key: string) {
    const i = newForm.value.team_keys.indexOf(key);
    if (i === -1) newForm.value.team_keys.push(key);
    else newForm.value.team_keys.splice(i, 1);
}

function submitNew() {
    if (!newForm.value.name.trim()) {
        newError.value = 'Name is required.';
        return;
    }
    newSubmitting.value = true;
    newError.value = null;
    const payload: Record<string, FormDataConvertible> = {
        name: newForm.value.name.trim(),
        state: newForm.value.state,
    };
    if (newForm.value.description.trim() !== '') {
        payload.description = newForm.value.description.trim();
    }
    if (newForm.value.team_keys.length > 0) {
        payload.team_keys = newForm.value.team_keys;
    }
    if (newForm.value.lead_user_id !== null) {
        payload.lead_user_id = newForm.value.lead_user_id;
    }
    if (newForm.value.start_date !== '') {
        payload.start_date = newForm.value.start_date;
    }
    if (newForm.value.target_date !== '') {
        payload.target_date = newForm.value.target_date;
    }
    router.post('/projects', payload, {
        onSuccess: () => {
            newDialogOpen.value = false;
            newSubmitting.value = false;
        },
        onError: (errors) => {
            newSubmitting.value = false;
            const first = Object.values(errors)[0];
            newError.value = (first as string | undefined) ?? 'Could not create project.';
        },
        onFinish: () => {
            newSubmitting.value = false;
        },
    });
}

const activeFilterCount = computed<number>(() => {
    let c = 0;
    if (props.filters.status) c++;
    if (props.filters.lead) c++;
    return c;
});
</script>

<template>
    <Head :title="headerTitle" />

    <div class="flex h-full flex-1 flex-col overflow-hidden">
        <header
            class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4 py-2.5"
        >
            <div class="flex min-w-0 items-center gap-2">
                <span
                    v-if="team"
                    class="flex size-5 items-center justify-center rounded-md text-[10px] font-semibold text-white"
                    :style="{ backgroundColor: team.color || '#6366f1' }"
                >
                    {{ team.key.charAt(0) }}
                </span>
                <h1 class="text-[13px] font-medium">{{ headerTitle }}</h1>
            </div>
            <button
                type="button"
                class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                aria-label="New project"
                title="New project"
                @click="openNew"
            >
                <Plus class="size-3.5" />
            </button>
        </header>

        <div
            class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4"
        >
            <nav class="flex items-center gap-1 py-2 text-[12.5px]">
                <button
                    type="button"
                    :class="[
                        'rounded-md px-2 py-1 transition-colors',
                        !favouritesView
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                    ]"
                    @click="applyParams({ fav: null })"
                >
                    All projects
                </button>
                <button
                    type="button"
                    :class="[
                        'flex items-center gap-1 rounded-md px-2 py-1 transition-colors',
                        favouritesView
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                    ]"
                    @click="applyParams({ fav: '1' })"
                >
                    <Star class="size-3" />
                    Favourites
                </button>
            </nav>
            <div class="flex items-center gap-1 text-muted-foreground">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            :class="[
                                'flex items-center gap-1 rounded-md p-1.5 transition-colors hover:bg-accent hover:text-foreground',
                                activeFilterCount > 0 && 'text-foreground',
                            ]"
                            aria-label="Filter"
                            title="Filter"
                        >
                            <SlidersHorizontal class="size-3.5" />
                            <span
                                v-if="activeFilterCount > 0"
                                class="text-[11px] tabular-nums"
                                >{{ activeFilterCount }}</span
                            >
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <DropdownMenuLabel>Filter by</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuSub>
                            <DropdownMenuSubTrigger>Status</DropdownMenuSubTrigger>
                            <DropdownMenuSubContent class="w-48">
                                <DropdownMenuCheckboxItem
                                    v-for="key in STATE_ORDER"
                                    :key="key"
                                    :model-value="props.filters.status === key"
                                    @select="(e) => { e.preventDefault(); setStatus(props.filters.status === key ? null : key); }"
                                >
                                    <span class="flex items-center gap-2">
                                        <StatusIcon :type="projectStatusType(key)" :size="12" />
                                        {{ STATE_LABELS[key] }}
                                    </span>
                                </DropdownMenuCheckboxItem>
                            </DropdownMenuSubContent>
                        </DropdownMenuSub>
                        <DropdownMenuSub>
                            <DropdownMenuSubTrigger>Lead</DropdownMenuSubTrigger>
                            <DropdownMenuSubContent class="max-h-72 w-56 overflow-y-auto">
                                <DropdownMenuCheckboxItem
                                    v-for="m in members"
                                    :key="m.id"
                                    :model-value="props.filters.lead === m.id"
                                    @select="(e) => { e.preventDefault(); setLead(props.filters.lead === m.id ? null : m.id); }"
                                >
                                    <span class="flex min-w-0 items-center gap-2">
                                        <Avatar :name="m.name" :email="m.email" :size="14" />
                                        <span class="truncate">{{ m.name }}</span>
                                    </span>
                                </DropdownMenuCheckboxItem>
                                <DropdownMenuItem
                                    v-if="!members.length"
                                    disabled
                                >
                                    No members
                                </DropdownMenuItem>
                            </DropdownMenuSubContent>
                        </DropdownMenuSub>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            :disabled="activeFilterCount === 0"
                            @select="clearFilters"
                        >
                            Clear filters
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="rounded-md p-1.5 transition-colors hover:bg-accent hover:text-foreground"
                            aria-label="Display"
                            title="Display"
                        >
                            <LayoutGrid class="size-3.5" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <DropdownMenuLabel>Group by</DropdownMenuLabel>
                        <DropdownMenuRadioGroup
                            :model-value="props.filters.group"
                            @update:model-value="(v) => setGroup(v as Filters['group'])"
                        >
                            <DropdownMenuRadioItem value="none">None</DropdownMenuRadioItem>
                            <DropdownMenuRadioItem value="status">Status</DropdownMenuRadioItem>
                            <DropdownMenuRadioItem value="lead">Lead</DropdownMenuRadioItem>
                        </DropdownMenuRadioGroup>
                        <DropdownMenuSeparator />
                        <DropdownMenuLabel>Sort by</DropdownMenuLabel>
                        <DropdownMenuRadioGroup
                            :model-value="props.filters.sort"
                            @update:model-value="(v) => setSort(v as Filters['sort'])"
                        >
                            <DropdownMenuRadioItem value="status">Status</DropdownMenuRadioItem>
                            <DropdownMenuRadioItem value="name">Name</DropdownMenuRadioItem>
                            <DropdownMenuRadioItem value="target">Target date</DropdownMenuRadioItem>
                            <DropdownMenuRadioItem value="issues">Issues count</DropdownMenuRadioItem>
                        </DropdownMenuRadioGroup>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <div
            v-if="!visibleProjects.length"
            class="flex flex-1 items-center justify-center px-6 py-12 text-center"
        >
            <p class="text-sm text-muted-foreground">
                {{ favouritesView ? 'No favourite projects yet.' : 'No projects.' }}
            </p>
        </div>

        <div v-else class="flex-1 overflow-y-auto">
            <!-- Column header row -->
            <div
                class="sticky top-0 z-10 grid grid-cols-[1fr_120px_64px_180px_110px_70px_80px] items-center gap-4 border-b border-border bg-background px-4 py-2 text-[11px] font-medium uppercase tracking-wide text-muted-foreground"
            >
                <span>Name</span>
                <span>Health</span>
                <span></span>
                <span>Lead</span>
                <span>Target date</span>
                <span class="text-right">Issues</span>
                <span class="text-right">Status</span>
            </div>

            <section v-for="group in grouped" :key="group.key">
                <button
                    v-if="props.filters.group !== 'none'"
                    type="button"
                    class="sticky top-[34px] z-[5] flex w-full items-center gap-2 bg-muted/40 px-4 py-1.5 text-left backdrop-blur transition-colors hover:bg-muted/60"
                    @click="toggleGroup(group.key)"
                >
                    <component
                        :is="collapsedGroups.has(group.key) ? ChevronRight : ChevronDown"
                        class="size-3 text-muted-foreground"
                    />
                    <span class="text-[12.5px] font-medium text-foreground">{{ group.label }}</span>
                    <span class="text-[12px] text-muted-foreground tabular-nums">{{ group.projects.length }}</span>
                </button>

                <ul
                    v-show="!collapsedGroups.has(group.key)"
                    class="divide-y divide-border"
                >
                    <li
                        v-for="project in group.projects"
                        :key="project.id"
                        class="group/row relative"
                    >
                        <Link
                            :href="`/projects/${project.slug}`"
                            class="grid grid-cols-[1fr_120px_64px_180px_110px_70px_80px] items-center gap-4 px-4 py-2 hover:bg-accent/40"
                        >
                            <div class="flex min-w-0 items-center gap-2.5">
                                <ProjectIcon
                                    :icon="project.icon"
                                    :color="project.color"
                                    :size="18"
                                />
                                <span class="truncate text-[13px] text-foreground">{{ project.name }}</span>
                            </div>

                            <span class="inline-flex items-center gap-1.5 text-[12px] text-muted-foreground">
                                <span class="size-1.5 rounded-full bg-zinc-500"></span>
                                No updates
                            </span>

                            <span></span>

                            <div v-if="project.lead" class="flex min-w-0 items-center gap-2 text-[12.5px]">
                                <Avatar :name="project.lead.name" :email="project.lead.email" :size="18" />
                                <span class="truncate text-foreground">{{ project.lead.name }}</span>
                            </div>
                            <div v-else class="flex items-center gap-2 text-[12px] text-muted-foreground">
                                <span class="flex size-[18px] items-center justify-center rounded-full border border-dashed border-border"></span>
                                <span>No lead</span>
                            </div>

                            <span
                                v-if="project.target_date"
                                class="inline-flex items-center gap-1 text-[12px]"
                                :class="isOverdue(project.target_date, project.completed_at) ? 'text-rose-400' : 'text-muted-foreground'"
                            >
                                <Calendar class="size-3.5" />
                                {{ fmtDate(project.target_date) }}
                            </span>
                            <span v-else class="text-[12px] text-muted-foreground">—</span>

                            <span class="text-right text-[12.5px] text-muted-foreground tabular-nums">
                                {{ project.total_issues }}
                            </span>

                            <div class="flex items-center justify-end gap-1.5">
                                <svg
                                    width="14"
                                    height="14"
                                    viewBox="0 0 14 14"
                                    fill="none"
                                    class="shrink-0"
                                    aria-hidden="true"
                                >
                                    <circle cx="7" cy="7" r="5" stroke="#3f3f46" stroke-width="1.5" fill="none" />
                                    <circle
                                        cx="7"
                                        cy="7"
                                        r="5"
                                        fill="none"
                                        stroke-width="2"
                                        :stroke="ringStroke(project.progress, project.state)"
                                        :stroke-dasharray="`${ringC} ${ringC}`"
                                        :stroke-dashoffset="ringDashOffset(project.progress)"
                                        transform="rotate(-90 7 7)"
                                        stroke-linecap="butt"
                                    />
                                </svg>
                                <span class="text-[12px] text-foreground tabular-nums">{{ project.progress }}%</span>
                            </div>
                        </Link>
                        <button
                            type="button"
                            class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-1 transition-opacity"
                            :class="[
                                isFav(project.slug)
                                    ? 'text-amber-400 opacity-100 hover:bg-accent'
                                    : 'text-muted-foreground opacity-0 hover:bg-accent hover:text-foreground group-hover/row:opacity-100',
                            ]"
                            :aria-label="isFav(project.slug) ? 'Unfavourite' : 'Favourite'"
                            :title="isFav(project.slug) ? 'Unfavourite' : 'Favourite'"
                            @click.stop="toggleFav(project.slug, $event)"
                        >
                            <Star
                                class="size-3.5"
                                :fill="isFav(project.slug) ? 'currentColor' : 'none'"
                            />
                        </button>
                    </li>
                </ul>
            </section>
        </div>

        <!-- New project dialog -->
        <Dialog v-model:open="newDialogOpen">
            <DialogContent class="sm:max-w-[520px]">
                <DialogHeader>
                    <DialogTitle>New project</DialogTitle>
                    <DialogDescription>
                        Create a project to organise issues across teams.
                    </DialogDescription>
                </DialogHeader>
                <form
                    class="space-y-4"
                    @submit.prevent="submitNew"
                >
                    <div class="space-y-1">
                        <label
                            class="text-[12px] font-medium text-foreground"
                            for="np-name"
                            >Name</label
                        >
                        <Input
                            id="np-name"
                            v-model="newForm.name"
                            type="text"
                            placeholder="e.g. Q3 onboarding overhaul"
                            class="h-8 text-[13px]"
                            autofocus
                        />
                    </div>
                    <div class="space-y-1">
                        <label
                            class="text-[12px] font-medium text-foreground"
                            for="np-desc"
                            >Description</label
                        >
                        <textarea
                            id="np-desc"
                            v-model="newForm.description"
                            rows="3"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-[13px] outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50"
                            placeholder="Why this project exists, what success looks like…"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label
                                class="text-[12px] font-medium text-foreground"
                                for="np-status"
                                >Status</label
                            >
                            <select
                                id="np-status"
                                v-model="newForm.state"
                                class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-[13px] capitalize"
                            >
                                <option v-for="key in STATE_ORDER" :key="key" :value="key">
                                    {{ STATE_LABELS[key] }}
                                </option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label
                                class="text-[12px] font-medium text-foreground"
                                for="np-lead"
                                >Lead</label
                            >
                            <select
                                id="np-lead"
                                v-model="newForm.lead_user_id"
                                class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-[13px]"
                            >
                                <option :value="null">No lead</option>
                                <option
                                    v-for="m in members"
                                    :key="m.id"
                                    :value="m.id"
                                >
                                    {{ m.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <span class="text-[12px] font-medium text-foreground"
                            >Teams</span
                        >
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="t in workspaceTeams"
                                :key="t.id"
                                type="button"
                                :class="[
                                    'flex items-center gap-1 rounded-md border px-2 py-0.5 text-[12px] transition-colors',
                                    newForm.team_keys.includes(t.key)
                                        ? 'border-foreground bg-accent text-foreground'
                                        : 'border-border text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                                ]"
                                @click="toggleNewTeam(t.key)"
                            >
                                <Check
                                    v-if="newForm.team_keys.includes(t.key)"
                                    class="size-3"
                                />
                                <span
                                    class="flex size-3.5 items-center justify-center rounded-sm text-[9px] font-semibold text-white"
                                    :style="{ backgroundColor: t.color || '#6366f1' }"
                                >
                                    {{ t.key.charAt(0) }}
                                </span>
                                {{ t.key }}
                            </button>
                            <span
                                v-if="!workspaceTeams.length"
                                class="text-[12px] text-muted-foreground"
                            >
                                No teams in this workspace.
                            </span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label
                                class="text-[12px] font-medium text-foreground"
                                for="np-start"
                                >Start date</label
                            >
                            <input
                                id="np-start"
                                v-model="newForm.start_date"
                                type="date"
                                class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-[13px]"
                            />
                        </div>
                        <div class="space-y-1">
                            <label
                                class="text-[12px] font-medium text-foreground"
                                for="np-target"
                                >Target date</label
                            >
                            <input
                                id="np-target"
                                v-model="newForm.target_date"
                                type="date"
                                class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-[13px]"
                            />
                        </div>
                    </div>
                    <p
                        v-if="newError"
                        class="text-[12px] text-rose-400"
                    >
                        {{ newError }}
                    </p>
                    <DialogFooter>
                        <DialogClose
                            class="rounded-md px-3 py-1.5 text-[13px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        >
                            Cancel
                        </DialogClose>
                        <button
                            type="submit"
                            :disabled="newSubmitting"
                            class="rounded-md bg-foreground px-3 py-1.5 text-[13px] font-medium text-background transition-opacity hover:opacity-90 disabled:opacity-50"
                        >
                            {{ newSubmitting ? 'Creating…' : 'Create project' }}
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
