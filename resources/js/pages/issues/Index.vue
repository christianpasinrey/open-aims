<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Bell, ChevronDown, ChevronRight, Plus, Star } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import Avatar from '@/components/repo/Avatar.vue';
import DisplayMenu from '@/components/repo/issues/DisplayMenu.vue';
import FilterMenu from '@/components/repo/issues/FilterMenu.vue';
import InlineComposer from '@/components/repo/issues/InlineComposer.vue';
import SaveViewButton from '@/components/repo/views/SaveViewButton.vue';
import LabelBadge from '@/components/repo/LabelBadge.vue';
import PriorityIcon from '@/components/repo/PriorityIcon.vue';
import ProjectChip from '@/components/repo/ProjectChip.vue';
import ProjectIcon from '@/components/repo/ProjectIcon.vue';
import StatusIcon from '@/components/repo/StatusIcon.vue';
import { startedProgressByState } from '@/lib/states';

type State = {
    id: number;
    name: string;
    type: string;
    color: string;
    position: number;
};
type Label = { id: number; name: string; color?: string | null };
type Assignee = { id: number; name: string; email: string };
type Project = {
    id: number;
    name: string;
    slug?: string | null;
    color?: string | null;
    icon?: string | null;
};
type Issue = {
    id: number;
    identifier: string;
    number: number;
    title: string;
    priority: number;
    state_id: number;
    state: { name: string; type: string; color: string } | null;
    assignee: Assignee | null;
    project: Project | null;
    labels: Label[];
    updated_at: string | null;
};
type Team = { id: number; name: string; key: string; color: string | null };

type GroupKey = 'status' | 'priority' | 'assignee' | 'project';

const props = defineProps<{
    team: Team | null;
    states: State[];
    issues: Issue[];
    labels: Label[];
    projects: Project[];
    priorities: Record<string, string>;
    filters?: {
        team: string | null;
        assignee: string | null;
        state: string | null;
        priority: number | null;
        project: number | null;
        labels: number[];
        group: string;
        sort: string;
    };
}>();

const safeFilters = computed(() => ({
    team: props.filters?.team ?? null,
    assignee: props.filters?.assignee ?? null,
    state: props.filters?.state ?? null,
    priority: props.filters?.priority ?? null,
    project: props.filters?.project ?? null,
    labels: props.filters?.labels ?? [],
    group: (props.filters?.group ?? 'status') as GroupKey,
    sort: props.filters?.sort ?? 'priority',
}));

const workspacePage = usePage<{
    workspace: { teams: Array<{ id: number; name: string; key: string; color: string | null }> } | null;
}>();
const workspaceTeams = computed(() => workspacePage.props.workspace?.teams ?? []);

const TYPE_RANK: Record<string, number> = {
    triage: 0,
    started: 1,
    unstarted: 2,
    backlog: 3,
    completed: 4,
    canceled: 5,
};
const stateOrder = computed(() =>
    [...props.states].sort((a, b) => {
        const ta = TYPE_RANK[a.type] ?? 99;
        const tb = TYPE_RANK[b.type] ?? 99;

        if (ta !== tb) {
            return ta - tb;
        }

        return a.position - b.position;
    }),
);

const startedProgress = computed(() => startedProgressByState(props.states));

// ------- Grouping --------------------------------------------------------
type GroupBucket = {
    /** Stable id for collapse state + composer routing. */
    key: string;
    title: string;
    count: number;
    issues: Issue[];
    // Only one of these is populated, depending on the group dimension.
    state?: State;
    priority?: number;
    assignee?: Assignee | null;
    project?: Project | null;
};

const grouped = computed<GroupBucket[]>(() => {
    const g = safeFilters.value.group;

    if (g === 'priority') {
        const order = [1, 2, 3, 4, 0]; // Urgent → No priority
        const buckets = new Map<number, Issue[]>();

        for (const p of order) {
            buckets.set(p, []);
        }

        for (const i of props.issues) {
            const k = order.includes(i.priority) ? i.priority : 0;
            buckets.get(k)!.push(i);
        }

        return order
            .map<GroupBucket>((p) => ({
                key: `priority:${p}`,
                title: props.priorities[String(p)] ?? '',
                count: buckets.get(p)!.length,
                issues: buckets.get(p)!,
                priority: p,
            }))
            .filter((b) => b.count > 0);
    }

    if (g === 'assignee') {
        const buckets = new Map<
            string,
            { assignee: Assignee | null; issues: Issue[] }
        >();

        for (const i of props.issues) {
            const k = i.assignee ? String(i.assignee.id) : 'none';

            if (!buckets.has(k)) {
                buckets.set(k, { assignee: i.assignee, issues: [] });
            }

            buckets.get(k)!.issues.push(i);
        }

        const out: GroupBucket[] = [];
        // Assigned first (alpha), then Unassigned at the end.
        const assigned = [...buckets.entries()]
            .filter(([k]) => k !== 'none')
            .sort(([, a], [, b]) =>
                (a.assignee?.name ?? '').localeCompare(b.assignee?.name ?? ''),
            );

        for (const [k, v] of assigned) {
            out.push({
                key: `assignee:${k}`,
                title: v.assignee?.name ?? 'Unknown',
                count: v.issues.length,
                issues: v.issues,
                assignee: v.assignee,
            });
        }

        if (buckets.has('none')) {
            const v = buckets.get('none')!;
            out.push({
                key: 'assignee:none',
                title: 'Unassigned',
                count: v.issues.length,
                issues: v.issues,
                assignee: null,
            });
        }

        return out;
    }

    if (g === 'project') {
        const buckets = new Map<
            string,
            { project: Project | null; issues: Issue[] }
        >();

        for (const i of props.issues) {
            const k = i.project ? String(i.project.id) : 'none';

            if (!buckets.has(k)) {
                buckets.set(k, { project: i.project, issues: [] });
            }

            buckets.get(k)!.issues.push(i);
        }

        const out: GroupBucket[] = [];
        const projected = [...buckets.entries()]
            .filter(([k]) => k !== 'none')
            .sort(([, a], [, b]) =>
                (a.project?.name ?? '').localeCompare(b.project?.name ?? ''),
            );

        for (const [k, v] of projected) {
            out.push({
                key: `project:${k}`,
                title: v.project?.name ?? '',
                count: v.issues.length,
                issues: v.issues,
                project: v.project,
            });
        }

        if (buckets.has('none')) {
            const v = buckets.get('none')!;
            out.push({
                key: 'project:none',
                title: 'No project',
                count: v.issues.length,
                issues: v.issues,
                project: null,
            });
        }

        return out;
    }

    // status (default)
    const buckets = new Map<number, Issue[]>();

    for (const s of stateOrder.value) {
        buckets.set(s.id, []);
    }

    for (const i of props.issues) {
        const bucket = buckets.get(i.state_id);

        if (bucket) {
            bucket.push(i);
        }
    }

    return stateOrder.value
        .map<GroupBucket>((s) => ({
            key: `status:${s.id}`,
            title: s.name,
            count: buckets.get(s.id)?.length ?? 0,
            issues: buckets.get(s.id) ?? [],
            state: s,
        }))
        .filter((b) => b.count > 0);
});

const totalIssues = computed(() => props.issues.length);

const headerLabel = computed<string>(() => {
    if (safeFilters.value.assignee === 'me') {
        return 'My Issues';
    }

    if (safeFilters.value.assignee === 'unassigned') {
        return 'Unassigned';
    }

    return 'All issues';
});

// ------- Tabs ------------------------------------------------------------
const tabs = computed(() => {
    const base = props.team ? `?team=${props.team.key}` : '';

    return [
        { key: null, label: 'All issues', href: `/issues${base}` },
        {
            key: 'active',
            label: 'Active',
            href: `/issues${base}${base ? '&' : '?'}state=started`,
        },
        {
            key: 'backlog',
            label: 'Backlog',
            href: `/issues${base}${base ? '&' : '?'}state=backlog`,
        },
    ];
});
const activeTab = computed<string | null>(() => {
    const s = safeFilters.value.state;

    if (s === 'started') {
        return 'active';
    }

    if (s === 'backlog') {
        return 'backlog';
    }

    return null;
});

function relativeTime(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const d = new Date(iso);

    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

// ------- Collapse persisted in localStorage -----------------------------
const collapsed = ref<Set<string>>(new Set());

function collapseStorageKey(groupKey: string): string {
    const team = props.team?.key ?? '';

    return `aims:collapsed:/issues?team=${team}:group=${safeFilters.value.group}:groupKey=${groupKey}`;
}

function loadCollapsed(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const next = new Set<string>();

    for (const b of grouped.value) {
        try {
            if (
                window.localStorage.getItem(collapseStorageKey(b.key)) === '1'
            ) {
                next.add(b.key);
            }
        } catch {
            // ignore quota / private mode errors.
        }
    }

    collapsed.value = next;
}

function toggleGroup(groupKey: string): void {
    const next = new Set(collapsed.value);

    if (next.has(groupKey)) {
        next.delete(groupKey);

        try {
            window.localStorage.removeItem(collapseStorageKey(groupKey));
        } catch {
            // ignore
        }
    } else {
        next.add(groupKey);

        try {
            window.localStorage.setItem(collapseStorageKey(groupKey), '1');
        } catch {
            // ignore
        }
    }

    collapsed.value = next;
}

onMounted(loadCollapsed);
watch(
    () => [grouped.value.map((g) => g.key).join('|'), safeFilters.value.group],
    loadCollapsed,
);

// ------- Favourite (star) ----------------------------------------------
function favouriteKey(): string {
    return `aims:favourites:/issues?team=${props.team?.key ?? ''}`;
}
const favourited = ref(false);
function loadFavourite(): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        favourited.value = window.localStorage.getItem(favouriteKey()) === '1';
    } catch {
        favourited.value = false;
    }
}
function toggleFavourite(): void {
    favourited.value = !favourited.value;

    try {
        if (favourited.value) {
            window.localStorage.setItem(favouriteKey(), '1');
        } else {
            window.localStorage.removeItem(favouriteKey());
        }
    } catch {
        // ignore quota / private mode errors.
    }
}
onMounted(loadFavourite);
watch(() => props.team?.key, loadFavourite);

// ------- Inline composer ------------------------------------------------
const composerOpen = ref<string | null>(null); // group key currently composing

function openComposer(groupKey: string): void {
    composerOpen.value = groupKey;
}
function closeComposer(): void {
    composerOpen.value = null;
}

function composerContext(
    bucket: GroupBucket,
): Record<string, string | number | null> {
    const ctx: Record<string, string | number | null> = {};
    const g = safeFilters.value.group;

    if (g === 'status' && bucket.state) {
        ctx.state_id = bucket.state.id;
    } else if (g === 'priority' && bucket.priority !== undefined) {
        ctx.priority = bucket.priority;
    } else if (g === 'assignee' && bucket.assignee) {
        ctx.assignee_user_id = bucket.assignee.id;
    } else if (g === 'project' && bucket.project) {
        ctx.project_id = bucket.project.id;
    }

    return ctx;
}

// ------- Cmd/Ctrl+click on identifier to copy ---------------------------
function onIdentifierClick(e: MouseEvent, identifier: string): void {
    if (!(e.metaKey || e.ctrlKey)) {
        return;
    }

    e.preventDefault();
    e.stopPropagation();

    if (typeof navigator !== 'undefined' && navigator.clipboard) {
        navigator.clipboard
            .writeText(identifier)
            .then(() => toast.success(`Copied ${identifier}`))
            .catch(() => toast.error('Could not copy'));
    }
}
</script>

<template>
    <Head :title="team ? `${team.name} · ${headerLabel}` : headerLabel" />

    <div class="flex h-full flex-1 flex-col overflow-hidden">
        <!-- Top bar -->
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
                <h1 class="text-[13px] font-medium text-foreground">
                    {{ headerLabel }}
                </h1>
                <button
                    type="button"
                    class="transition-colors"
                    :class="
                        favourited
                            ? 'text-amber-400 hover:text-amber-300'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    aria-label="Favourite"
                    @click="toggleFavourite"
                >
                    <Star
                        class="size-3.5"
                        :fill="favourited ? 'currentColor' : 'none'"
                    />
                </button>
                <SaveViewButton :filters="safeFilters" :teams="workspaceTeams" />
            </div>
            <Link
                href="/inbox"
                class="text-muted-foreground transition-colors hover:text-foreground"
                aria-label="Notifications"
            >
                <Bell class="size-3.5" />
            </Link>
        </header>

        <!-- Tabs + filter pills -->
        <div
            class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4"
        >
            <nav class="flex items-center gap-1 py-2 text-[12.5px]">
                <Link
                    v-for="tab in tabs"
                    :key="tab.label"
                    :href="tab.href"
                    :class="[
                        'rounded-md px-2 py-1 transition-colors',
                        activeTab === tab.key
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                    ]"
                >
                    {{ tab.label }}
                </Link>
            </nav>
            <div class="flex items-center gap-1 text-muted-foreground">
                <FilterMenu
                    :team-key="team?.key ?? null"
                    :states="states"
                    :labels="labels"
                    :projects="projects"
                    :priorities="priorities"
                    :filters="safeFilters"
                />
                <DisplayMenu :filters="safeFilters" />
            </div>
        </div>

        <!-- Empty -->
        <div
            v-if="!team || totalIssues === 0"
            class="flex flex-1 items-center justify-center px-6 py-12 text-center"
        >
            <div class="max-w-sm">
                <h2 class="text-base font-medium text-foreground">
                    No issues yet
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Run
                    <code
                        class="rounded bg-muted px-1.5 py-0.5 font-mono text-[12px]"
                        >php artisan aims:import-snapshot</code
                    >
                    to populate the workspace.
                </p>
            </div>
        </div>

        <!-- Grouped list -->
        <div v-else class="flex-1 overflow-y-auto">
            <section v-for="group in grouped" :key="group.key">
                <div
                    class="sticky top-0 z-10 flex w-full items-center gap-2 bg-muted/40 px-4 py-1.5 text-left backdrop-blur"
                >
                    <button
                        type="button"
                        class="flex flex-1 items-center gap-2 transition-colors hover:text-foreground"
                        @click="toggleGroup(group.key)"
                    >
                        <component
                            :is="
                                collapsed.has(group.key)
                                    ? ChevronRight
                                    : ChevronDown
                            "
                            class="size-3 text-muted-foreground"
                        />
                        <!-- Status group icon -->
                        <StatusIcon
                            v-if="safeFilters.group === 'status' && group.state"
                            :type="group.state.type"
                            :color="group.state.color"
                            :progress="startedProgress[group.state.id]"
                            :size="14"
                        />
                        <!-- Priority group icon -->
                        <PriorityIcon
                            v-else-if="
                                safeFilters.group === 'priority' &&
                                group.priority !== undefined
                            "
                            :priority="group.priority"
                            :size="14"
                        />
                        <!-- Assignee avatar -->
                        <template v-else-if="safeFilters.group === 'assignee'">
                            <Avatar
                                v-if="group.assignee"
                                :name="group.assignee.name"
                                :email="group.assignee.email"
                                :size="16"
                            />
                            <span
                                v-else
                                class="size-3.5 rounded-full border border-dashed border-border"
                            ></span>
                        </template>
                        <!-- Project icon -->
                        <template v-else-if="safeFilters.group === 'project'">
                            <ProjectIcon
                                v-if="group.project"
                                :icon="group.project.icon"
                                :color="group.project.color"
                                :size="14"
                            />
                            <span
                                v-else
                                class="size-3.5 rounded-full border border-dashed border-border"
                            ></span>
                        </template>

                        <span
                            class="text-[12.5px] font-medium text-foreground"
                            >{{ group.title }}</span
                        >
                        <span class="text-[12px] text-muted-foreground">{{
                            group.count
                        }}</span>
                    </button>
                    <button
                        v-if="team"
                        type="button"
                        class="rounded p-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        aria-label="New issue in this group"
                        @click.stop="openComposer(group.key)"
                    >
                        <Plus class="size-3.5" />
                    </button>
                </div>

                <InlineComposer
                    v-if="team"
                    :open="composerOpen === group.key"
                    :team-key="team.key"
                    :context="composerContext(group)"
                    @close="closeComposer"
                />

                <ul
                    v-show="!collapsed.has(group.key)"
                    class="divide-y divide-border"
                >
                    <li v-for="issue in group.issues" :key="issue.id">
                        <Link
                            :href="`/issues/${issue.identifier}`"
                            class="grid grid-cols-[auto_auto_64px_1fr_auto_auto_42px_24px] items-center gap-2 px-4 py-1.5 hover:bg-accent/40"
                        >
                            <PriorityIcon
                                :priority="issue.priority"
                                :size="14"
                            />

                            <span
                                class="font-mono text-[11px] text-muted-foreground tabular-nums"
                                @click="
                                    onIdentifierClick($event, issue.identifier)
                                "
                                >{{ issue.identifier }}</span
                            >

                            <StatusIcon
                                :type="issue.state?.type ?? 'unstarted'"
                                :color="issue.state?.color"
                                :progress="startedProgress[issue.state_id]"
                                :size="14"
                            />

                            <span
                                class="min-w-0 truncate text-[13px] text-foreground"
                                >{{ issue.title }}</span
                            >

                            <div
                                v-if="issue.labels.length"
                                class="hidden shrink-0 items-center gap-1 lg:flex"
                            >
                                <LabelBadge
                                    v-for="label in issue.labels.slice(0, 3)"
                                    :key="label.id"
                                    :name="label.name"
                                    :color="label.color"
                                />
                                <span
                                    v-if="issue.labels.length > 3"
                                    class="text-[11px] text-muted-foreground"
                                    >+{{ issue.labels.length - 3 }}</span
                                >
                            </div>
                            <span v-else></span>

                            <ProjectChip
                                v-if="issue.project"
                                :name="issue.project.name"
                                :color="issue.project.color"
                                :icon="issue.project.icon"
                                :slug="issue.project.slug"
                                class="hidden md:inline-flex"
                            />
                            <span v-else></span>

                            <span
                                class="text-right text-[11px] text-muted-foreground tabular-nums"
                            >
                                {{ relativeTime(issue.updated_at) }}
                            </span>

                            <Avatar
                                v-if="issue.assignee"
                                :name="issue.assignee.name"
                                :email="issue.assignee.email"
                                :size="20"
                            />
                            <span
                                v-else
                                class="flex size-5 items-center justify-center rounded-full border border-dashed border-border text-muted-foreground"
                                title="Unassigned"
                                aria-label="Unassigned"
                            ></span>
                        </Link>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
