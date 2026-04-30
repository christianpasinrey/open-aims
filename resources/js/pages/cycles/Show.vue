<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Bell,
    ChevronDown,
    MoreHorizontal,
    Play,
    Plus,
    Star,
} from 'lucide-vue-next';
import StatusIcon from '@/components/repo/StatusIcon.vue';
import PriorityIcon from '@/components/repo/PriorityIcon.vue';
import Avatar from '@/components/repo/Avatar.vue';
import LabelBadge from '@/components/repo/LabelBadge.vue';
import ProjectChip from '@/components/repo/ProjectChip.vue';

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
    updated_at: string | null;
};
type AssigneeRow = {
    user: { id: number; name: string; email: string } | null;
    completed: number;
    total: number;
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
    states: State[];
    issues: Issue[];
}>();

const typeOrder: Record<string, number> = {
    triage: 0,
    started: 1,
    unstarted: 2,
    backlog: 3,
    completed: 4,
    canceled: 5,
};

const stateOrder = computed(() =>
    [...props.states].sort((a, b) => {
        const ta = typeOrder[a.type] ?? 99;
        const tb = typeOrder[b.type] ?? 99;
        if (ta !== tb) return ta - tb;
        return a.position - b.position;
    }),
);

const grouped = computed(() => {
    const buckets = new Map<number, Issue[]>();
    for (const s of stateOrder.value) buckets.set(s.id, []);
    for (const i of props.issues) {
        const bucket = buckets.get(i.state_id);
        if (bucket) bucket.push(i);
    }
    return stateOrder.value
        .map((s) => ({ state: s, issues: buckets.get(s.id) ?? [] }))
        .filter((g) => g.issues.length > 0);
});

const activeRailTab = ref<'assignees' | 'labels' | 'priority' | 'projects'>(
    'assignees',
);

function relativeTime(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

function fmtShort(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

const dateRangeLabel = computed<string>(() => {
    const s = fmtShort(props.cycle.starts_at);
    const e = fmtShort(props.cycle.ends_at);
    if (!s && !e) return '';
    return `${s} → ${e}`;
});

// Donut ring helpers (mirrors projects/Show.vue)
const ringR = 5;
const ringC = 2 * Math.PI * ringR;
function ringDashOffsetFor(percent: number): number {
    return ringC * (1 - Math.max(0, Math.min(100, percent)) / 100);
}
function ringStrokeFor(percent: number): string {
    if (percent >= 100) return '#10b981';
    if (percent > 0) return '#6366f1';
    return '#a1a1aa';
}

// Burndown chart sample data: simple monotonic series derived from progress.percent.
// Two polylines: target (linear from total → 0) and actual (slower based on completed).
const chartW = 240;
const chartH = 140;
const chartPad = 8;

function buildPoints(values: number[], maxVal: number): string {
    if (values.length === 0) return '';
    const innerW = chartW - chartPad * 2;
    const innerH = chartH - chartPad * 2;
    const maxV = Math.max(1, maxVal);
    return values
        .map((v, idx) => {
            const x = chartPad + (innerW * idx) / Math.max(1, values.length - 1);
            const y = chartPad + innerH * (1 - v / maxV);
            return `${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');
}

const chartData = computed(() => {
    const total = Math.max(1, props.progress.total);
    const steps = 7;
    const target: number[] = [];
    const actual: number[] = [];
    const pct = props.progress.percent / 100;
    // Actual line: starts at total, ends at total*(1-pct) (remaining open)
    const remaining = total * (1 - pct);
    for (let i = 0; i <= steps; i++) {
        const t = i / steps;
        target.push(total * (1 - t));
        // Slight curve; lerp between total and remaining with ease
        const ease = t * t * (3 - 2 * t);
        actual.push(total - (total - remaining) * ease);
    }
    return {
        target: buildPoints(target, total),
        actual: buildPoints(actual, total),
    };
});

const startedPercent = computed<number>(() => {
    if (props.progress.total === 0) return 0;
    return Math.round((props.progress.started / props.progress.total) * 100);
});
</script>

<template>
    <Head :title="`${team.name} · ${cycle.name}`" />

    <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden">
        <!-- Top bar -->
        <header
            class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4 py-2.5"
        >
            <nav class="flex min-w-0 items-center gap-2 text-[12.5px]">
                <Play class="size-3.5 shrink-0 fill-indigo-500 text-indigo-500" />
                <span class="text-foreground">Cycle {{ cycle.number }}</span>
                <ChevronDown class="size-3 text-muted-foreground" />
                <button
                    type="button"
                    class="text-muted-foreground transition-colors hover:text-foreground"
                    aria-label="Favourite"
                >
                    <Star class="size-3.5" />
                </button>
            </nav>
            <div class="flex items-center gap-1 text-muted-foreground">
                <button
                    type="button"
                    class="rounded-md p-1.5 hover:bg-accent hover:text-foreground"
                    aria-label="Notifications"
                >
                    <Bell class="size-3.5" />
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

                <section
                    v-for="group in grouped"
                    v-else
                    :key="group.state.id"
                >
                    <div
                        class="sticky top-0 z-10 flex items-center gap-2 bg-muted/40 px-4 py-1.5 backdrop-blur"
                    >
                        <ChevronDown class="size-3 text-muted-foreground" />
                        <StatusIcon
                            :type="group.state.type"
                            :color="group.state.color"
                            :size="14"
                        />
                        <span class="text-[12.5px] font-medium text-foreground">{{
                            group.state.name
                        }}</span>
                        <span class="text-[12px] text-muted-foreground">{{
                            group.issues.length
                        }}</span>
                        <button
                            type="button"
                            class="ml-auto rounded p-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            aria-label="New issue in this status"
                        >
                            <Plus class="size-3.5" />
                        </button>
                    </div>

                    <ul class="divide-y divide-border">
                        <li v-for="issue in group.issues" :key="issue.id">
                            <Link
                                :href="`/issues/${issue.identifier}`"
                                class="grid grid-cols-[auto_auto_64px_1fr_auto_auto_42px_24px] items-center gap-2 px-4 py-1.5 hover:bg-accent/40"
                            >
                                <PriorityIcon :priority="issue.priority" :size="14" />

                                <span
                                    class="font-mono text-[11px] text-muted-foreground tabular-nums"
                                    >{{ issue.identifier }}</span
                                >

                                <StatusIcon
                                    :type="issue.state?.type ?? 'unstarted'"
                                    :color="issue.state?.color"
                                    :size="14"
                                />

                                <span class="min-w-0 truncate text-[13px] text-foreground">{{
                                    issue.title
                                }}</span>

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
                                    :href="issue.project.slug ? `/projects/${issue.project.slug}` : null"
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
                    <span class="text-[14px] font-semibold text-foreground"
                        >Cycle {{ cycle.number }}</span
                    >
                    <button
                        type="button"
                        class="ml-auto text-muted-foreground hover:text-foreground"
                        aria-label="Favourite"
                    >
                        <Star class="size-3.5" />
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
                    v-if="cycle.status === 'current' && cycle.weekdays_left !== null"
                    class="mt-1 text-[12px] text-muted-foreground"
                >
                    {{ cycle.weekdays_left }} weekday{{ cycle.weekdays_left === 1 ? '' : 's' }} left
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
                        class="mb-2 flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        <ChevronDown class="size-3" />
                        Progress
                    </div>

                    <!-- Stat cards -->
                    <div class="grid grid-cols-3 gap-2">
                        <div class="rounded-md border border-border bg-card p-2">
                            <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                <span class="size-1.5 rounded-full bg-zinc-500"></span>
                                Scope
                            </div>
                            <div class="mt-1 text-[16px] font-semibold tabular-nums text-foreground">
                                {{ progress.total }}
                            </div>
                            <div
                                v-if="progress.scope_change_percent !== null"
                                class="text-[11px] text-muted-foreground tabular-nums"
                            >
                                +{{ progress.scope_change_percent }}%
                            </div>
                        </div>
                        <div class="rounded-md border border-border bg-card p-2">
                            <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                <span class="size-1.5 rounded-full bg-yellow-500"></span>
                                Started
                            </div>
                            <div class="mt-1 text-[16px] font-semibold tabular-nums text-foreground">
                                {{ progress.started }}
                            </div>
                            <div class="text-[11px] text-muted-foreground tabular-nums">
                                · {{ startedPercent }}%
                            </div>
                        </div>
                        <div class="rounded-md border border-border bg-card p-2">
                            <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                <span class="size-1.5 rounded-full bg-indigo-500"></span>
                                Completed
                            </div>
                            <div class="mt-1 text-[16px] font-semibold tabular-nums text-foreground">
                                {{ progress.completed }}
                            </div>
                            <div class="text-[11px] text-muted-foreground tabular-nums">
                                · {{ progress.percent }}%
                            </div>
                        </div>
                    </div>

                    <!-- Burndown chart placeholder -->
                    <div
                        class="mt-3 flex h-[180px] items-end justify-around rounded-md border border-border bg-card px-3 pb-3"
                    >
                        <svg
                            :width="chartW"
                            :height="chartH"
                            :viewBox="`0 0 ${chartW} ${chartH}`"
                            class="w-full"
                        >
                            <polyline
                                :points="chartData.target"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1"
                                stroke-dasharray="3 3"
                                class="text-muted-foreground/50"
                            />
                            <polyline
                                :points="chartData.actual"
                                fill="none"
                                stroke="#6366f1"
                                stroke-width="1.5"
                            />
                        </svg>
                    </div>
                    <div
                        class="mt-1 flex justify-between text-[10.5px] text-muted-foreground tabular-nums"
                    >
                        <span>{{ fmtShort(cycle.starts_at) }}</span>
                        <span>{{ fmtShort(cycle.ends_at) }}</span>
                    </div>
                </div>

                <!-- Tabs: Assignees | Labels | Priority | Projects -->
                <div
                    class="mt-5 flex items-center gap-1 border-b border-border text-[12px]"
                >
                    <button
                        v-for="t in (['assignees','labels','priority','projects'] as const)"
                        :key="t"
                        type="button"
                        :class="[
                            'rounded-t-md px-2 py-1.5 transition-colors -mb-px border-b-2',
                            activeRailTab === t
                                ? 'border-foreground text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        ]"
                        @click="activeRailTab = t"
                    >
                        <span class="capitalize">{{ t }}</span>
                    </button>
                </div>

                <!-- Assignees list -->
                <div v-if="activeRailTab === 'assignees'" class="mt-3 space-y-2">
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
                        <span class="min-w-0 flex-1 truncate text-[12.5px] text-foreground">
                            {{ row.user?.name ?? 'Unassigned' }}
                        </span>
                        <span class="text-[11px] text-muted-foreground tabular-nums">
                            {{ row.percent }}% of {{ row.total }}
                        </span>
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
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
                                :stroke-dashoffset="ringDashOffsetFor(row.percent)"
                                transform="rotate(-90 7 7)"
                            />
                        </svg>
                    </div>
                </div>

                <div
                    v-else
                    class="mt-3 text-[12px] text-muted-foreground"
                >
                    Coming soon
                </div>
            </aside>
        </div>
    </div>
</template>
