<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Bell,
    ChevronDown,
    ChevronRight,
    MoreHorizontal,
    Play,
    Plus,
    Star,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import Avatar from '@/components/repo/Avatar.vue';
import LabelBadge from '@/components/repo/LabelBadge.vue';
import PriorityIcon from '@/components/repo/PriorityIcon.vue';
import ProjectChip from '@/components/repo/ProjectChip.vue';
import ProjectIcon from '@/components/repo/ProjectIcon.vue';
import StatusIcon from '@/components/repo/StatusIcon.vue';
import { startedProgressByState } from '@/lib/states';

type Team = { id: number; name: string; key: string; color: string | null };
type State = {
    id: number;
    name: string;
    type: string;
    color: string;
    position: number;
};
type Label = { id: number; name: string; color?: string | null };
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
    assignee: { id: number; name: string; email: string } | null;
    project: Project | null;
    labels: Label[];
    completed_at: string | null;
    updated_at: string | null;
};
type AssigneeRow = {
    user: { id: number; name: string; email: string } | null;
    completed: number;
    total: number;
    percent: number;
};
type LabelBreakdownRow = {
    label: { id: number; name: string; color?: string | null };
    total: number;
    completed: number;
    percent: number;
};
type PriorityBreakdownRow = {
    priority: number;
    label: string;
    total: number;
    completed: number;
    percent: number;
};
type ProjectBreakdownRow = {
    project: Project | null;
    total: number;
    completed: number;
    percent: number;
};
type Cycle = {
    id: number;
    number: number;
    name: string;
    description: string | null;
    starts_at: string | null;
    ends_at: string | null;
    completed_at: string | null;
    status: 'current' | 'upcoming' | 'past' | 'completed';
    weekdays_left: number | null;
};

const props = defineProps<{
    team: Team;
    cycle: Cycle;
    progress: {
        total: number;
        completed: number;
        started: number;
        percent: number;
        scope_change_percent: number | null;
    };
    assignees: AssigneeRow[];
    labels_breakdown: LabelBreakdownRow[];
    priority_breakdown: PriorityBreakdownRow[];
    projects_breakdown: ProjectBreakdownRow[];
    states: State[];
    issues: Issue[];
}>();

// ─── State group ordering ───────────────────────────────────────────────
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
const grouped = computed(() => {
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
        .map((s) => ({ state: s, issues: buckets.get(s.id) ?? [] }))
        .filter((g) => g.issues.length > 0);
});
const startedProgress = computed(() => startedProgressByState(props.states));

// ─── Right-rail breakdown tabs ──────────────────────────────────────────
const activeRailTab = ref<'assignees' | 'labels' | 'priority' | 'projects'>(
    'assignees',
);

// ─── Date helpers ───────────────────────────────────────────────────────
function fmtShort(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}
function relativeTime(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}
function dayKey(iso: string): string {
    return iso.slice(0, 10);
}
function diffDaysInclusive(a: string, b: string): number {
    const da = new Date(a + 'T00:00:00');
    const db = new Date(b + 'T00:00:00');

    return Math.round((db.getTime() - da.getTime()) / 86400000) + 1;
}
function addDaysIso(iso: string, days: number): string {
    const d = new Date(iso + 'T00:00:00');
    d.setDate(d.getDate() + days);

    return d.toISOString().slice(0, 10);
}

const dateRangeLabel = computed<string>(() => {
    const s = fmtShort(props.cycle.starts_at);
    const e = fmtShort(props.cycle.ends_at);

    if (!s && !e) {
        return '';
    }

    return `${s} → ${e}`;
});

// ─── Donut ring ─────────────────────────────────────────────────────────
const ringR = 5;
const ringC = 2 * Math.PI * ringR;
function ringDashOffsetFor(percent: number): number {
    return ringC * (1 - Math.max(0, Math.min(100, percent)) / 100);
}
function ringStrokeFor(percent: number): string {
    if (percent >= 100) {
        return '#10b981';
    }

    if (percent > 0) {
        return '#6366f1';
    }

    return '#a1a1aa';
}

// ─── Burndown chart ─────────────────────────────────────────────────────
// We build "open issues per day" from completed_at. Days with no completion
// keep the previous count, so the actual line is monotonic (or equal) and
// ends with the count of issues still open today (or at cycle end).
const chartW = 256;
const chartH = 140;
const chartPadX = 12;
const chartPadTop = 14;
const chartPadBottom = 18;

type BurndownPoint = { x: number; y: number; date: string; open: number };

const burndown = computed<{
    target: BurndownPoint[];
    actual: BurndownPoint[];
    total: number;
    todayIndex: number | null;
}>(() => {
    const start = props.cycle.starts_at;
    const end = props.cycle.ends_at;
    const total = props.progress.total;

    if (!start || !end || total === 0) {
        return { target: [], actual: [], total, todayIndex: null };
    }

    const days = Math.max(1, diffDaysInclusive(start, end));
    const innerW = chartW - chartPadX * 2;
    const innerH = chartH - chartPadTop - chartPadBottom;
    const xFor = (i: number) =>
        chartPadX + (days <= 1 ? 0 : (innerW * i) / (days - 1));
    const yFor = (v: number) => chartPadTop + innerH * (1 - v / total);

    // Completion counts by day
    const completedByDay = new Map<string, number>();

    for (const issue of props.issues) {
        if (!issue.completed_at) {
            continue;
        }

        const k = dayKey(issue.completed_at);
        completedByDay.set(k, (completedByDay.get(k) ?? 0) + 1);
    }

    const todayIso = new Date().toISOString().slice(0, 10);

    const target: BurndownPoint[] = [];
    const actual: BurndownPoint[] = [];
    let open = total;
    let todayIndex: number | null = null;

    for (let i = 0; i < days; i++) {
        const date = addDaysIso(start, i);
        const tFrac = days <= 1 ? 1 : i / (days - 1);
        target.push({
            x: xFor(i),
            y: yFor(total * (1 - tFrac)),
            date,
            open: total * (1 - tFrac),
        });

        if (date <= todayIso) {
            const completedToday = completedByDay.get(date) ?? 0;
            open = Math.max(0, open - completedToday);
            actual.push({ x: xFor(i), y: yFor(open), date, open });
            todayIndex = i;
        }
    }

    return { target, actual, total, todayIndex };
});

const targetPath = computed<string>(() =>
    burndown.value.target
        .map((p) => `${p.x.toFixed(1)},${p.y.toFixed(1)}`)
        .join(' '),
);
const actualPath = computed<string>(() =>
    burndown.value.actual
        .map((p) => `${p.x.toFixed(1)},${p.y.toFixed(1)}`)
        .join(' '),
);

const startedPercent = computed<number>(() => {
    if (props.progress.total === 0) {
        return 0;
    }

    return Math.round((props.progress.started / props.progress.total) * 100);
});

// ─── Favourites (localStorage) ──────────────────────────────────────────
const FAV_KEY = `aims:fav-cycle:${props.team.key}:${props.cycle.id}`;
const cycleFav = ref(false);
function readFav(): boolean {
    try {
        return localStorage.getItem(FAV_KEY) === '1';
    } catch {
        return false;
    }
}
function writeFav(v: boolean) {
    try {
        localStorage.setItem(FAV_KEY, v ? '1' : '0');
    } catch {
        /* ignore */
    }
}
function toggleFav() {
    cycleFav.value = !cycleFav.value;
    writeFav(cycleFav.value);
}

// ─── Group collapse (localStorage) ──────────────────────────────────────
const COLLAPSE_KEY = `aims:cycle-groups-collapsed:${props.team.key}:${props.cycle.id}`;
const collapsed = ref<Set<number>>(new Set());
function readCollapsed(): Set<number> {
    try {
        const raw = localStorage.getItem(COLLAPSE_KEY);

        if (!raw) {
            return new Set();
        }

        const arr = JSON.parse(raw) as unknown;

        if (!Array.isArray(arr)) {
            return new Set();
        }

        return new Set(arr.filter((v): v is number => typeof v === 'number'));
    } catch {
        return new Set();
    }
}
function writeCollapsed(s: Set<number>) {
    try {
        localStorage.setItem(COLLAPSE_KEY, JSON.stringify([...s]));
    } catch {
        /* ignore */
    }
}
function toggleGroup(id: number) {
    const next = new Set(collapsed.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    collapsed.value = next;
    writeCollapsed(next);
}

onMounted(() => {
    cycleFav.value = readFav();
    collapsed.value = readCollapsed();
});

// ─── Inline composer (per group) ────────────────────────────────────────
const composerOpenFor = ref<number | null>(null);
const composerTitle = ref('');
const composerSubmitting = ref(false);
const composerInputRef = ref<HTMLInputElement | null>(null);

function openComposer(stateId: number) {
    composerOpenFor.value = stateId;
    composerTitle.value = '';
    void nextTick(() => {
        composerInputRef.value?.focus();
    });
}
function cancelComposer() {
    composerOpenFor.value = null;
    composerTitle.value = '';
}
function submitComposer(stateId: number) {
    const title = composerTitle.value.trim();

    if (!title || composerSubmitting.value) {
        return;
    }

    composerSubmitting.value = true;
    router.post(
        '/issues',
        {
            title,
            team_key: props.team.key,
            state_id: stateId,
            cycle_id: props.cycle.id,
        },
        {
            preserveScroll: true,
            preserveState: false,
            onFinish: () => {
                composerSubmitting.value = false;
                composerOpenFor.value = null;
                composerTitle.value = '';
            },
        },
    );
}

// ─── Inline-edit cycle name ─────────────────────────────────────────────
const editingName = ref(false);
const nameDraft = ref('');
const nameInputRef = ref<HTMLInputElement | null>(null);
const nameSubmitting = ref(false);

function startEditName() {
    nameDraft.value = props.cycle.name;
    editingName.value = true;
    void nextTick(() => {
        nameInputRef.value?.focus();
        nameInputRef.value?.select();
    });
}
function cancelEditName() {
    editingName.value = false;
    nameDraft.value = props.cycle.name;
}
function commitEditName() {
    const v = nameDraft.value.trim();

    if (!v || v === props.cycle.name || nameSubmitting.value) {
        editingName.value = false;

        return;
    }

    nameSubmitting.value = true;
    router.patch(
        `/cycles/${props.cycle.number}?team=${encodeURIComponent(props.team.key)}`,
        { name: v },
        {
            preserveScroll: true,
            onFinish: () => {
                nameSubmitting.value = false;
                editingName.value = false;
            },
        },
    );
}
watch(
    () => props.cycle.name,
    (n) => {
        if (!editingName.value) {
            nameDraft.value = n;
        }
    },
);

// ─── Priority bar colors (mirror PriorityIcon palette) ──────────────────
function priorityRowColor(p: number): string {
    switch (p) {
        case 1:
            return '#f87171';
        case 2:
            return '#f97316';
        case 3:
            return '#f59e0b';
        case 4:
            return '#eab308';
        default:
            return '#94a3b8';
    }
}
</script>

<template>
    <Head :title="`${team.name} · ${cycle.name}`" />

    <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden">
        <!-- Top bar -->
        <header
            class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4 py-2.5"
        >
            <nav class="flex min-w-0 items-center gap-2 text-[12.5px]">
                <Link
                    :href="`/cycles?team=${team.key}`"
                    class="flex shrink-0 items-center gap-1.5 text-muted-foreground transition-colors hover:text-foreground"
                >
                    <span
                        class="flex size-4 items-center justify-center rounded-md text-[9px] font-semibold text-white"
                        :style="{ backgroundColor: team.color || '#6366f1' }"
                    >
                        {{ team.key.charAt(0) }}
                    </span>
                    <span>{{ team.name }}</span>
                </Link>
                <span class="text-muted-foreground">›</span>
                <Play
                    class="size-3.5 shrink-0 fill-indigo-500 text-indigo-500"
                />
                <span class="text-foreground">Cycle {{ cycle.number }}</span>
                <button
                    type="button"
                    :class="[
                        'transition-colors',
                        cycleFav
                            ? 'text-amber-400 hover:text-amber-500'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                    :aria-label="cycleFav ? 'Unfavourite' : 'Favourite'"
                    @click="toggleFav"
                >
                    <Star
                        class="size-3.5"
                        :fill="cycleFav ? 'currentColor' : 'none'"
                    />
                </button>
            </nav>
            <div class="flex items-center gap-1 text-muted-foreground">
                <Link
                    href="/inbox"
                    class="rounded-md p-1.5 transition-colors hover:bg-accent hover:text-foreground"
                    aria-label="Inbox"
                    title="Inbox"
                >
                    <Bell class="size-3.5" />
                </Link>
                <button
                    type="button"
                    class="rounded-md p-1.5 transition-colors hover:bg-accent hover:text-foreground"
                    aria-label="More"
                    title="More"
                >
                    <MoreHorizontal class="size-3.5" />
                </button>
            </div>
        </header>

        <!-- Subtitle: count -->
        <div class="shrink-0 px-4 py-2 text-[12px] text-muted-foreground">
            {{ progress.total }} {{ progress.total === 1 ? 'issue' : 'issues' }}
        </div>

        <!-- Body: split with right rail -->
        <div class="flex min-h-0 flex-1">
            <!-- Main column: issue list grouped by status -->
            <div class="flex min-w-0 flex-1 flex-col overflow-y-auto">
                <div
                    v-if="!issues.length"
                    class="flex h-full items-center justify-center px-6 py-12 text-center"
                >
                    <p class="text-sm text-muted-foreground">
                        No issues in this cycle.
                    </p>
                </div>

                <section v-for="group in grouped" v-else :key="group.state.id">
                    <button
                        type="button"
                        class="sticky top-0 z-10 flex w-full items-center gap-2 bg-muted/40 px-4 py-1.5 text-left backdrop-blur transition-colors hover:bg-muted/60"
                        @click="toggleGroup(group.state.id)"
                    >
                        <component
                            :is="
                                collapsed.has(group.state.id)
                                    ? ChevronRight
                                    : ChevronDown
                            "
                            class="size-3 text-muted-foreground"
                        />
                        <StatusIcon
                            :type="group.state.type"
                            :color="group.state.color"
                            :progress="startedProgress[group.state.id]"
                            :size="14"
                        />
                        <span
                            class="text-[12.5px] font-medium text-foreground"
                            >{{ group.state.name }}</span
                        >
                        <span class="text-[12px] text-muted-foreground">{{
                            group.issues.length
                        }}</span>
                        <span
                            class="ml-auto rounded p-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            aria-label="New issue in this status"
                            @click.stop="openComposer(group.state.id)"
                        >
                            <Plus class="size-3.5" />
                        </span>
                    </button>

                    <div
                        v-if="composerOpenFor === group.state.id"
                        class="flex items-center gap-2 border-b border-border bg-card px-4 py-1.5"
                    >
                        <StatusIcon
                            :type="group.state.type"
                            :color="group.state.color"
                            :progress="startedProgress[group.state.id]"
                            :size="14"
                        />
                        <input
                            ref="composerInputRef"
                            v-model="composerTitle"
                            type="text"
                            placeholder="Issue title"
                            class="min-w-0 flex-1 bg-transparent text-[13px] text-foreground outline-none placeholder:text-muted-foreground"
                            @keydown.enter.prevent="
                                submitComposer(group.state.id)
                            "
                            @keydown.escape="cancelComposer"
                        />
                        <button
                            type="button"
                            class="rounded-md px-2 py-0.5 text-[11.5px] text-muted-foreground hover:bg-accent hover:text-foreground"
                            @click="cancelComposer"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="rounded-md bg-indigo-500 px-2 py-0.5 text-[11.5px] font-medium text-white transition-colors hover:bg-indigo-600 disabled:opacity-50"
                            :disabled="
                                composerSubmitting || !composerTitle.trim()
                            "
                            @click="submitComposer(group.state.id)"
                        >
                            {{ composerSubmitting ? 'Saving…' : 'Create' }}
                        </button>
                    </div>

                    <ul
                        v-show="!collapsed.has(group.state.id)"
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
                                        v-for="label in issue.labels.slice(
                                            0,
                                            3,
                                        )"
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
                                    :href="
                                        issue.project.slug
                                            ? `/projects/${issue.project.slug}`
                                            : null
                                    "
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

            <!-- Right rail -->
            <aside
                class="hidden w-[300px] shrink-0 overflow-y-auto border-l border-border bg-muted/20 px-5 py-5 lg:block"
            >
                <!-- Tabs row: Current button + date range pill -->
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md bg-accent px-2 py-1 text-[12px] font-medium text-foreground"
                    >
                        <Play class="size-3 fill-indigo-500 text-indigo-500" />
                        Current
                    </button>
                    <span
                        v-if="dateRangeLabel"
                        class="inline-flex items-center rounded-md border border-border bg-card px-2 py-1 text-[11.5px] text-muted-foreground"
                    >
                        {{ dateRangeLabel }}
                    </span>
                </div>

                <!-- Cycle title block -->
                <div class="mt-5 flex items-center gap-2">
                    <Play class="size-4 fill-indigo-500 text-indigo-500" />
                    <input
                        v-if="editingName"
                        ref="nameInputRef"
                        v-model="nameDraft"
                        type="text"
                        class="min-w-0 flex-1 rounded-sm bg-transparent text-[14px] font-semibold text-foreground ring-1 ring-indigo-500/40 ring-offset-1 ring-offset-background outline-none"
                        @blur="commitEditName"
                        @keydown.enter.prevent="commitEditName"
                        @keydown.escape="cancelEditName"
                    />
                    <button
                        v-else
                        type="button"
                        class="min-w-0 flex-1 truncate text-left text-[14px] font-semibold text-foreground transition-colors hover:text-foreground/80"
                        :title="cycle.name"
                        @click="startEditName"
                    >
                        {{ cycle.name }}
                    </button>
                    <button
                        type="button"
                        :class="[
                            'transition-colors',
                            cycleFav
                                ? 'text-amber-400 hover:text-amber-500'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                        aria-label="Favourite"
                        @click="toggleFav"
                    >
                        <Star
                            class="size-3.5"
                            :fill="cycleFav ? 'currentColor' : 'none'"
                        />
                    </button>
                    <button
                        type="button"
                        class="text-muted-foreground hover:text-foreground"
                        aria-label="More"
                    >
                        <MoreHorizontal class="size-3.5" />
                    </button>
                </div>

                <div
                    v-if="
                        cycle.status === 'current' &&
                        cycle.weekdays_left !== null
                    "
                    class="mt-1 text-[12px] text-muted-foreground"
                >
                    {{ cycle.weekdays_left }} weekday{{
                        cycle.weekdays_left === 1 ? '' : 's'
                    }}
                    left
                </div>

                <button
                    type="button"
                    class="mt-3 flex w-full items-center gap-2 rounded-md border border-dashed border-border px-3 py-2 text-[12.5px] text-muted-foreground transition-colors hover:bg-accent/40 hover:text-foreground"
                >
                    <Plus class="size-3.5" /> Add document or link…
                </button>

                <!-- Progress section -->
                <div class="mt-6">
                    <div
                        class="mb-2 flex items-center gap-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        <ChevronDown class="size-3" />
                        Progress
                    </div>

                    <!-- Stat cards -->
                    <div class="grid grid-cols-3 gap-2">
                        <div
                            class="rounded-md border border-border bg-card p-2"
                        >
                            <div
                                class="flex items-center gap-1.5 text-[11px] text-muted-foreground"
                            >
                                <span
                                    class="size-1.5 rounded-full bg-zinc-500"
                                ></span>
                                Scope
                            </div>
                            <div
                                class="mt-1 text-[16px] font-semibold text-foreground tabular-nums"
                            >
                                {{ progress.total }}
                            </div>
                            <div
                                v-if="progress.scope_change_percent !== null"
                                class="text-[11px] text-muted-foreground tabular-nums"
                            >
                                +{{ progress.scope_change_percent }}%
                            </div>
                        </div>
                        <div
                            class="rounded-md border border-border bg-card p-2"
                        >
                            <div
                                class="flex items-center gap-1.5 text-[11px] text-muted-foreground"
                            >
                                <span
                                    class="size-1.5 rounded-full bg-amber-400"
                                ></span>
                                Started
                            </div>
                            <div
                                class="mt-1 text-[16px] font-semibold text-foreground tabular-nums"
                            >
                                {{ progress.started }}
                            </div>
                            <div
                                class="text-[11px] text-muted-foreground tabular-nums"
                            >
                                · {{ startedPercent }}%
                            </div>
                        </div>
                        <div
                            class="rounded-md border border-border bg-card p-2"
                        >
                            <div
                                class="flex items-center gap-1.5 text-[11px] text-muted-foreground"
                            >
                                <span
                                    class="size-1.5 rounded-full bg-indigo-500"
                                ></span>
                                Completed
                            </div>
                            <div
                                class="mt-1 text-[16px] font-semibold text-foreground tabular-nums"
                            >
                                {{ progress.completed }}
                            </div>
                            <div
                                class="text-[11px] text-muted-foreground tabular-nums"
                            >
                                · {{ progress.percent }}%
                            </div>
                        </div>
                    </div>

                    <!-- Burndown chart -->
                    <div
                        class="relative mt-3 h-[160px] rounded-md border border-border bg-card"
                    >
                        <svg
                            :viewBox="`0 0 ${chartW} ${chartH}`"
                            preserveAspectRatio="none"
                            class="absolute inset-0 h-full w-full"
                            aria-hidden="true"
                        >
                            <!-- Y gridlines: 0 (bottom) and total (top) -->
                            <line
                                :x1="chartPadX"
                                :y1="chartPadTop"
                                :x2="chartW - chartPadX"
                                :y2="chartPadTop"
                                stroke="currentColor"
                                stroke-width="0.5"
                                class="text-border"
                            />
                            <line
                                :x1="chartPadX"
                                :y1="chartH - chartPadBottom"
                                :x2="chartW - chartPadX"
                                :y2="chartH - chartPadBottom"
                                stroke="currentColor"
                                stroke-width="0.5"
                                class="text-border"
                            />
                            <!-- Y labels: total (top), 0 (bottom) -->
                            <text
                                :x="chartPadX - 2"
                                :y="chartPadTop + 3"
                                text-anchor="end"
                                font-size="8"
                                class="fill-muted-foreground tabular-nums"
                            >
                                {{ progress.total }}
                            </text>
                            <text
                                :x="chartPadX - 2"
                                :y="chartH - chartPadBottom + 3"
                                text-anchor="end"
                                font-size="8"
                                class="fill-muted-foreground tabular-nums"
                            >
                                0
                            </text>

                            <!-- Ideal -->
                            <polyline
                                v-if="targetPath"
                                :points="targetPath"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1"
                                stroke-dasharray="3 3"
                                class="text-muted-foreground/60"
                            />
                            <!-- Actual -->
                            <polyline
                                v-if="actualPath"
                                :points="actualPath"
                                fill="none"
                                stroke="#6366f1"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                            <!-- Dot markers on actual -->
                            <circle
                                v-for="(p, i) in burndown.actual"
                                :key="`actual-${i}`"
                                :cx="p.x"
                                :cy="p.y"
                                r="1.5"
                                fill="#6366f1"
                            />
                        </svg>
                        <div
                            class="pointer-events-none absolute inset-x-3 bottom-1 flex items-center justify-between text-[10px] text-muted-foreground tabular-nums"
                        >
                            <span>{{ fmtShort(cycle.starts_at) }}</span>
                            <span>{{ fmtShort(cycle.ends_at) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Tabs: Assignees | Labels | Priority | Projects -->
                <div
                    class="mt-5 flex items-center gap-1 border-b border-border text-[12px]"
                >
                    <button
                        v-for="t in [
                            'assignees',
                            'labels',
                            'priority',
                            'projects',
                        ] as const"
                        :key="t"
                        type="button"
                        :class="[
                            '-mb-px rounded-t-md border-b-2 px-2 py-1.5 transition-colors',
                            activeRailTab === t
                                ? 'border-foreground text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        ]"
                        @click="activeRailTab = t"
                    >
                        <span class="capitalize">{{ t }}</span>
                    </button>
                </div>

                <!-- Assignees -->
                <div
                    v-if="activeRailTab === 'assignees'"
                    class="mt-3 space-y-2"
                >
                    <div
                        v-if="!assignees.length"
                        class="text-[12px] text-muted-foreground"
                    >
                        No assignees yet.
                    </div>
                    <div
                        v-for="(row, idx) in assignees"
                        :key="row.user?.id ?? `unassigned-${idx}`"
                        class="flex items-center gap-2"
                    >
                        <Avatar
                            v-if="row.user"
                            :name="row.user.name"
                            :email="row.user.email"
                            :size="20"
                        />
                        <span
                            v-else
                            class="flex size-5 items-center justify-center rounded-full border border-dashed border-border text-muted-foreground"
                            aria-label="Unassigned"
                        ></span>
                        <span
                            class="min-w-0 flex-1 truncate text-[12.5px] text-foreground"
                        >
                            {{ row.user?.name ?? 'Unassigned' }}
                        </span>
                        <span
                            class="text-[11px] text-muted-foreground tabular-nums"
                        >
                            {{ row.percent }}% of {{ row.total }}
                        </span>
                        <svg
                            width="14"
                            height="14"
                            viewBox="0 0 14 14"
                            fill="none"
                        >
                            <circle
                                cx="7"
                                cy="7"
                                r="5"
                                stroke="#3f3f46"
                                stroke-width="1.5"
                                fill="none"
                            />
                            <circle
                                cx="7"
                                cy="7"
                                r="5"
                                fill="none"
                                stroke-width="2"
                                :stroke="ringStrokeFor(row.percent)"
                                :stroke-dasharray="`${ringC} ${ringC}`"
                                :stroke-dashoffset="
                                    ringDashOffsetFor(row.percent)
                                "
                                transform="rotate(-90 7 7)"
                            />
                        </svg>
                    </div>
                </div>

                <!-- Labels -->
                <div
                    v-else-if="activeRailTab === 'labels'"
                    class="mt-3 space-y-2"
                >
                    <div
                        v-if="!labels_breakdown.length"
                        class="text-[12px] text-muted-foreground"
                    >
                        No labelled issues.
                    </div>
                    <div
                        v-for="row in labels_breakdown"
                        :key="row.label.id"
                        class="flex items-center gap-2"
                    >
                        <LabelBadge
                            :name="row.label.name"
                            :color="row.label.color"
                        />
                        <span
                            class="ml-auto text-[11px] text-muted-foreground tabular-nums"
                        >
                            {{ row.percent }}% of {{ row.total }}
                        </span>
                        <svg
                            width="14"
                            height="14"
                            viewBox="0 0 14 14"
                            fill="none"
                        >
                            <circle
                                cx="7"
                                cy="7"
                                r="5"
                                stroke="#3f3f46"
                                stroke-width="1.5"
                                fill="none"
                            />
                            <circle
                                cx="7"
                                cy="7"
                                r="5"
                                fill="none"
                                stroke-width="2"
                                :stroke="ringStrokeFor(row.percent)"
                                :stroke-dasharray="`${ringC} ${ringC}`"
                                :stroke-dashoffset="
                                    ringDashOffsetFor(row.percent)
                                "
                                transform="rotate(-90 7 7)"
                            />
                        </svg>
                    </div>
                </div>

                <!-- Priority -->
                <div
                    v-else-if="activeRailTab === 'priority'"
                    class="mt-3 space-y-2"
                >
                    <div
                        v-if="!priority_breakdown.length"
                        class="text-[12px] text-muted-foreground"
                    >
                        No issues.
                    </div>
                    <div
                        v-for="row in priority_breakdown"
                        :key="row.priority"
                        class="flex items-center gap-2"
                    >
                        <PriorityIcon :priority="row.priority" :size="14" />
                        <span
                            class="min-w-0 flex-1 truncate text-[12.5px]"
                            :style="{ color: priorityRowColor(row.priority) }"
                            >{{ row.label }}</span
                        >
                        <span
                            class="text-[11px] text-muted-foreground tabular-nums"
                        >
                            {{ row.percent }}% of {{ row.total }}
                        </span>
                        <svg
                            width="14"
                            height="14"
                            viewBox="0 0 14 14"
                            fill="none"
                        >
                            <circle
                                cx="7"
                                cy="7"
                                r="5"
                                stroke="#3f3f46"
                                stroke-width="1.5"
                                fill="none"
                            />
                            <circle
                                cx="7"
                                cy="7"
                                r="5"
                                fill="none"
                                stroke-width="2"
                                :stroke="ringStrokeFor(row.percent)"
                                :stroke-dasharray="`${ringC} ${ringC}`"
                                :stroke-dashoffset="
                                    ringDashOffsetFor(row.percent)
                                "
                                transform="rotate(-90 7 7)"
                            />
                        </svg>
                    </div>
                </div>

                <!-- Projects -->
                <div v-else class="mt-3 space-y-2">
                    <div
                        v-if="!projects_breakdown.length"
                        class="text-[12px] text-muted-foreground"
                    >
                        No projects.
                    </div>
                    <div
                        v-for="(row, idx) in projects_breakdown"
                        :key="row.project?.id ?? `none-${idx}`"
                        class="flex items-center gap-2"
                    >
                        <ProjectIcon
                            v-if="row.project"
                            :icon="row.project.icon"
                            :color="row.project.color"
                            :size="16"
                            rounded="sm"
                        />
                        <span
                            v-else
                            class="size-4 rounded-sm border border-dashed border-border"
                        ></span>
                        <span
                            class="min-w-0 flex-1 truncate text-[12.5px] text-foreground"
                        >
                            {{ row.project?.name ?? 'No project' }}
                        </span>
                        <span
                            class="text-[11px] text-muted-foreground tabular-nums"
                        >
                            {{ row.percent }}% of {{ row.total }}
                        </span>
                        <svg
                            width="14"
                            height="14"
                            viewBox="0 0 14 14"
                            fill="none"
                        >
                            <circle
                                cx="7"
                                cy="7"
                                r="5"
                                stroke="#3f3f46"
                                stroke-width="1.5"
                                fill="none"
                            />
                            <circle
                                cx="7"
                                cy="7"
                                r="5"
                                fill="none"
                                stroke-width="2"
                                :stroke="ringStrokeFor(row.percent)"
                                :stroke-dasharray="`${ringC} ${ringC}`"
                                :stroke-dashoffset="
                                    ringDashOffsetFor(row.percent)
                                "
                                transform="rotate(-90 7 7)"
                            />
                        </svg>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
