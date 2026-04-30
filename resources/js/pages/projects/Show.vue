<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Calendar,
    ChevronDown,
    Diamond,
    Flag,
    Plus,
    SlidersHorizontal,
    LayoutGrid,
    Star,
    Bell,
    MoreHorizontal,
    Link as LinkIcon,
    UserPlus,
    Package,
} from 'lucide-vue-next';
import StatusIcon from '@/components/repo/StatusIcon.vue';
import PriorityIcon from '@/components/repo/PriorityIcon.vue';
import ProjectIcon from '@/components/repo/ProjectIcon.vue';
import Avatar from '@/components/repo/Avatar.vue';
import LabelBadge from '@/components/repo/LabelBadge.vue';
import { renderMarkdown } from '@/lib/markdown';

type Project = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    state: string | null;
    color: string | null;
    icon: string | null;
    start_date: string | null;
    target_date: string | null;
    completed_at: string | null;
    lead: { id: number; name: string; email: string } | null;
    members: Array<{ id: number; name: string; email: string; role: string | null }>;
    milestones: Array<{
        id: number;
        name: string;
        description: string | null;
        target_date: string | null;
        issue_count: number;
        percent: number;
    }>;
    teams: Array<{ id: number; name: string; key: string; color: string | null }>;
};
type IssueState = { name: string; type: string; color: string };
type Label = { id: number; name: string; color?: string | null };
type Issue = {
    id: number;
    identifier: string;
    title: string;
    priority: number;
    state_name: string | null;
    state: IssueState | null;
    assignee: { id: number; name: string } | null;
    labels: Label[];
    updated_at: string | null;
};
type State = {
    id: number;
    name: string;
    type: string;
    color: string;
    position: number;
};
type AssigneeStat = {
    user: { id: number; name: string; email: string } | null;
    total: number;
    completed: number;
    percent: number;
};

const props = defineProps<{
    project: Project;
    issues: Issue[];
    states: State[];
    progress: { total: number; completed: number; started: number; percent: number };
    assignees: AssigneeStat[];
    labels: Array<{ id: number; name: string; color?: string | null }>;
    tab: 'overview' | 'activity' | 'issues';
}>();

const descriptionHtml = computed<string>(() =>
    renderMarkdown(props.project.description),
);

const stateOrder = computed(() =>
    [...props.states].sort((a, b) => a.position - b.position),
);
const grouped = computed(() => {
    const buckets = new Map<string, Issue[]>();
    for (const i of props.issues) {
        const key = i.state_name ?? '—';
        if (!buckets.has(key)) buckets.set(key, []);
        buckets.get(key)!.push(i);
    }
    const ordered: Array<{ state: State | { name: string; type: string; color: string; position: number }; issues: Issue[] }> = [];
    for (const s of stateOrder.value) {
        const bucket = buckets.get(s.name);
        if (bucket && bucket.length) {
            ordered.push({ state: s, issues: bucket });
            buckets.delete(s.name);
        }
    }
    for (const [name, list] of buckets.entries()) {
        ordered.push({
            state: { name, type: 'unstarted', color: '#94a3b8', position: 999 },
            issues: list,
        });
    }
    return ordered;
});

const teamForBreadcrumb = computed(() => props.project.teams[0] ?? null);

function fmtDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric',
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

function tabHref(tab: 'overview' | 'activity' | 'issues') {
    return tab === 'overview'
        ? `/projects/${props.project.slug}`
        : `/projects/${props.project.slug}?tab=${tab}`;
}

const ringR = 5;
const ringC = 2 * Math.PI * ringR;
const ringDashOffset = computed(
    () => ringC * (1 - props.progress.percent / 100),
);
const ringStroke = computed(() => {
    if (props.project.state === 'canceled') return '#a1a1aa';
    if (props.progress.percent >= 100 || props.project.state === 'completed')
        return '#10b981';
    if (props.progress.percent > 0) return '#f59e0b';
    return '#a1a1aa';
});

// Map project.state → StatusIcon type
const projectStatusType = computed<'backlog' | 'started' | 'unstarted' | 'completed' | 'canceled'>(() => {
    switch (props.project.state) {
        case 'started':
            return 'started';
        case 'paused':
            return 'unstarted';
        case 'completed':
            return 'completed';
        case 'canceled':
            return 'canceled';
        case 'planned':
        case 'backlog':
        default:
            return 'backlog';
    }
});

function projectStateLabel() {
    return props.project.state ?? 'backlog';
}

// ---- Right-rail Progress > tabs ----
const progressTab = ref<'assignees' | 'labels' | 'cycles'>('assignees');

// Burndown chart geometry. We render a 256x140 viewBox; the polylines
// are derived from the project progress percentage so the chart always
// reflects the live data without persisting per-day points.
const burndownIdealPoints = '12,18 244,122';
const burndownActualPoints = computed<string>(() => {
    const startX = 12;
    const endX = 244;
    const topY = 18;
    const bottomY = 122;
    const total = props.progress.total;
    const completed = props.progress.completed;
    if (total <= 0) {
        return `${startX},${topY} ${endX},${topY}`;
    }
    const t = Math.max(0, Math.min(1, completed / total));
    const segs = 6;
    const pts: string[] = [];
    for (let i = 0; i <= segs; i++) {
        const x = startX + (i / segs) * (endX - startX);
        // Slight ease-in so the line reads as a real burndown
        const eased = Math.pow(i / segs, 1.4) * t;
        const y = topY + eased * (bottomY - topY);
        pts.push(`${x.toFixed(1)},${y.toFixed(1)}`);
    }
    return pts.join(' ');
});

// Donut math for per-assignee progress
const donutR = 5;
const donutC = 2 * Math.PI * donutR;
function donutOffset(percent: number): number {
    return donutC * (1 - Math.max(0, Math.min(100, percent)) / 100);
}
function donutStroke(percent: number): string {
    if (percent >= 100) return '#10b981';
    if (percent > 0) return '#6366f1';
    return '#a1a1aa';
}
</script>

<template>
    <Head :title="project.name" />

    <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden">
        <!-- Top bar with breadcrumb -->
        <header
            class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4 py-2.5"
        >
            <nav class="flex min-w-0 items-center gap-2 text-[12.5px]">
                <Link
                    v-if="teamForBreadcrumb"
                    :href="`/projects?team=${teamForBreadcrumb.key}`"
                    class="flex shrink-0 items-center gap-1.5 text-muted-foreground transition-colors hover:text-foreground"
                >
                    <span
                        class="flex size-4 items-center justify-center rounded-md text-[9px] font-semibold text-white"
                        :style="{
                            backgroundColor: teamForBreadcrumb.color || '#6366f1',
                        }"
                    >
                        {{ teamForBreadcrumb.key.charAt(0) }}
                    </span>
                    <span>{{ teamForBreadcrumb.name }}</span>
                </Link>
                <span class="text-muted-foreground">›</span>
                <ProjectIcon
                    :icon="project.icon"
                    :color="project.color"
                    :size="14"
                    rounded="sm"
                />
                <h1 class="truncate text-foreground">{{ project.name }}</h1>
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
                    aria-label="Copy link"
                >
                    <LinkIcon class="size-3.5" />
                </button>
                <button
                    type="button"
                    class="rounded-md p-1.5 hover:bg-accent hover:text-foreground"
                    aria-label="Notifications"
                >
                    <Bell class="size-3.5" />
                </button>
                <button
                    type="button"
                    class="rounded-md p-1.5 hover:bg-accent hover:text-foreground"
                    aria-label="More"
                >
                    <MoreHorizontal class="size-3.5" />
                </button>
            </div>
        </header>

        <!-- Tabs -->
        <div
            class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4"
        >
            <nav class="flex items-center gap-1 py-2 text-[12.5px]">
                <Link
                    :href="tabHref('overview')"
                    :class="[
                        'rounded-md px-2 py-1 transition-colors',
                        tab === 'overview'
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                    ]"
                    >Overview</Link
                >
                <Link
                    :href="tabHref('activity')"
                    :class="[
                        'rounded-md px-2 py-1 transition-colors',
                        tab === 'activity'
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                    ]"
                    >Activity</Link
                >
                <Link
                    :href="tabHref('issues')"
                    :class="[
                        'rounded-md px-2 py-1 transition-colors',
                        tab === 'issues'
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                    ]"
                    >Issues</Link
                >
            </nav>
            <div v-if="tab === 'issues'" class="flex items-center gap-1 text-muted-foreground">
                <button
                    type="button"
                    class="rounded-md p-1.5 hover:bg-accent hover:text-foreground"
                    aria-label="Filter"
                >
                    <SlidersHorizontal class="size-3.5" />
                </button>
                <button
                    type="button"
                    class="rounded-md p-1.5 hover:bg-accent hover:text-foreground"
                    aria-label="Display"
                >
                    <LayoutGrid class="size-3.5" />
                </button>
            </div>
        </div>

        <!-- Body: split with right rail -->
        <div class="flex min-h-0 flex-1">
            <div class="flex min-w-0 flex-1 flex-col overflow-y-auto">
                <!-- OVERVIEW -->
                <div v-if="tab === 'overview'" class="mx-auto w-full max-w-3xl px-8 py-8">
                    <ProjectIcon
                        :icon="project.icon"
                        :color="project.color"
                        :size="40"
                        rounded="lg"
                        class="mb-4"
                    />
                    <h2 class="text-[22px] font-semibold tracking-tight">{{ project.name }}</h2>
                    <p
                        v-if="project.description"
                        class="mt-2 text-[14px] text-muted-foreground"
                    >
                        {{ (project.description.split('\n').find(l => l.trim().length > 0) ?? '') }}
                    </p>

                    <!-- Properties row -->
                    <div class="mt-6 flex flex-wrap items-center gap-2 text-[12.5px]">
                        <span class="text-muted-foreground">Properties</span>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-2 py-0.5 text-foreground"
                        >
                            <StatusIcon :type="projectStatusType" />
                            <span class="capitalize">{{ projectStateLabel() }}</span>
                        </span>
                        <span
                            v-if="project.lead"
                            class="inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-2 py-0.5 text-foreground"
                        >
                            <Avatar :name="project.lead.name" :email="project.lead.email" :size="16" />
                            <span>{{ project.lead.name }}</span>
                        </span>
                        <span
                            v-if="project.target_date"
                            class="inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-2 py-0.5 text-muted-foreground"
                        >
                            <Calendar class="size-3" />
                            {{ fmtDate(project.target_date) }}
                        </span>
                    </div>

                    <!-- Resources stub -->
                    <div class="mt-6">
                        <div class="mb-2 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Resources</div>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded-md border border-dashed border-border px-3 py-2 text-[13px] text-muted-foreground transition-colors hover:bg-accent/40 hover:text-foreground"
                        >
                            <Plus class="size-3.5" /> Add document or link…
                        </button>
                    </div>

                    <!-- Update CTA -->
                    <div
                        class="mt-6 flex items-center justify-center rounded-md border border-border bg-muted/30 px-4 py-6 text-[13px] text-muted-foreground"
                    >
                        Write first project update
                    </div>

                    <!-- Description -->
                    <section v-if="descriptionHtml" class="mt-8">
                        <div class="mb-3 flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                            Description <ChevronDown class="size-3" />
                        </div>
                        <div class="markdown-body" v-html="descriptionHtml"></div>
                    </section>

                    <section v-if="project.milestones.length" class="mt-10">
                        <h3 class="mb-3 text-[12px] font-medium uppercase tracking-wide text-muted-foreground">Milestones</h3>
                        <ul class="divide-y divide-border rounded-md border border-border">
                            <li v-for="ms in project.milestones" :key="ms.id" class="px-3 py-2">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-[13px] font-medium">{{ ms.name }}</span>
                                    <span v-if="ms.target_date" class="text-[12px] text-muted-foreground">{{ fmtShort(ms.target_date) }}</span>
                                </div>
                                <p v-if="ms.description" class="mt-1 text-[12.5px] text-muted-foreground">{{ ms.description }}</p>
                            </li>
                        </ul>
                    </section>
                </div>

                <!-- ACTIVITY (placeholder) -->
                <div
                    v-else-if="tab === 'activity'"
                    class="flex flex-1 items-center justify-center px-6 py-12 text-center"
                >
                    <div class="max-w-sm">
                        <h2 class="text-base font-medium text-foreground">No activity yet</h2>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Project updates and changes will appear here.
                        </p>
                    </div>
                </div>

                <!-- ISSUES -->
                <div v-else class="flex-1 overflow-y-auto">
                    <div
                        v-if="!issues.length"
                        class="flex h-full items-center justify-center px-6 py-12 text-center"
                    >
                        <p class="text-sm text-muted-foreground">No issues in this project.</p>
                    </div>
                    <section
                        v-for="group in grouped"
                        :key="group.state.name"
                    >
                        <div
                            class="sticky top-0 z-10 flex items-center gap-2 bg-muted/40 px-4 py-1.5 backdrop-blur"
                        >
                            <ChevronDown class="size-3 text-muted-foreground" />
                            <StatusIcon
                                :type="group.state.type"
                                :color="group.state.color"
                            />
                            <span class="text-[12.5px] font-medium text-foreground">{{ group.state.name }}</span>
                            <span class="text-[12px] text-muted-foreground">{{ group.issues.length }}</span>
                            <button
                                type="button"
                                class="ml-auto rounded p-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                aria-label="New issue"
                            >
                                <Plus class="size-3.5" />
                            </button>
                        </div>
                        <ul class="divide-y divide-border">
                            <li v-for="issue in group.issues" :key="issue.id">
                                <Link
                                    :href="`/issues/${issue.identifier}`"
                                    class="grid grid-cols-[auto_auto_64px_1fr_auto_42px_24px] items-center gap-2 px-4 py-1.5 hover:bg-accent/40"
                                >
                                    <PriorityIcon :priority="issue.priority" />
                                    <span class="font-mono text-[11px] text-muted-foreground tabular-nums">{{ issue.identifier }}</span>
                                    <StatusIcon
                                        :type="issue.state?.type ?? 'unstarted'"
                                        :color="issue.state?.color"
                                    />
                                    <span class="min-w-0 truncate text-[13px]">{{ issue.title }}</span>
                                    <div class="hidden items-center gap-1 lg:flex">
                                        <LabelBadge
                                            v-for="label in issue.labels.slice(0, 2)"
                                            :key="label.id"
                                            :name="label.name"
                                            :color="label.color"
                                        />
                                    </div>
                                    <span class="text-right text-[11px] text-muted-foreground tabular-nums">
                                        {{ fmtShort(issue.updated_at) }}
                                    </span>
                                    <Avatar
                                        v-if="issue.assignee"
                                        :name="issue.assignee.name"
                                        :size="20"
                                    />
                                    <span
                                        v-else
                                        class="size-5 rounded-full border border-dashed border-border"
                                    ></span>
                                </Link>
                            </li>
                        </ul>
                    </section>
                </div>
            </div>

            <!-- Right rail -->
            <aside
                class="hidden w-[280px] shrink-0 overflow-y-auto border-l border-border bg-muted/20 px-5 py-5 lg:block"
            >
                <div class="space-y-6 text-[13px]">
                    <!-- ============== PROPERTIES ============== -->
                    <section>
                        <header class="mb-2 flex items-center justify-between">
                            <button
                                type="button"
                                class="flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground hover:text-foreground"
                            >
                                Properties <ChevronDown class="size-3" />
                            </button>
                            <button
                                type="button"
                                class="rounded p-0.5 text-muted-foreground hover:bg-accent hover:text-foreground"
                                aria-label="Add property"
                            >
                                <Plus class="size-3" />
                            </button>
                        </header>
                        <dl class="grid grid-cols-[80px_1fr] items-center gap-x-3 gap-y-2">
                            <!-- Status -->
                            <dt class="text-[12.5px] text-muted-foreground">Status</dt>
                            <dd class="flex items-center gap-1.5 text-[13px] text-foreground">
                                <StatusIcon :type="projectStatusType" />
                                <span class="capitalize">{{ projectStateLabel() }}</span>
                            </dd>

                            <!-- Priority -->
                            <dt class="text-[12.5px] text-muted-foreground">Priority</dt>
                            <dd class="flex items-center gap-1.5 text-[13px] text-foreground">
                                <PriorityIcon :priority="0" />
                                <span class="text-muted-foreground">No priority</span>
                            </dd>

                            <!-- Lead -->
                            <dt class="text-[12.5px] text-muted-foreground">Lead</dt>
                            <dd v-if="project.lead" class="flex items-center gap-1.5 text-[13px] text-foreground">
                                <Avatar :name="project.lead.name" :email="project.lead.email" :size="16" />
                                <span class="truncate">{{ project.lead.name }}</span>
                            </dd>
                            <dd v-else class="flex items-center gap-1.5 text-[13px] text-muted-foreground">
                                <span class="size-4 rounded-full border border-dashed border-border"></span>
                                <span>No lead</span>
                            </dd>

                            <!-- Members -->
                            <dt class="text-[12.5px] text-muted-foreground">Members</dt>
                            <dd>
                                <div v-if="project.members.length" class="flex items-center -space-x-1">
                                    <span
                                        v-for="m in project.members.slice(0, 5)"
                                        :key="m.id"
                                        class="ring-2 ring-[hsl(var(--background))]"
                                    >
                                        <Avatar :name="m.name" :email="m.email" :size="18" />
                                    </span>
                                    <span
                                        v-if="project.members.length > 5"
                                        class="ml-1 text-[11px] text-muted-foreground tabular-nums"
                                    >+{{ project.members.length - 5 }}</span>
                                </div>
                                <button
                                    v-else
                                    type="button"
                                    class="inline-flex items-center gap-1.5 text-[12.5px] text-muted-foreground hover:text-foreground"
                                >
                                    <UserPlus class="size-3.5" />
                                    Add members
                                </button>
                            </dd>

                            <!-- Dates: 📅 start → 🚩 target -->
                            <dt class="text-[12.5px] text-muted-foreground">Dates</dt>
                            <dd class="flex flex-wrap items-center gap-1.5 text-[12.5px]">
                                <span
                                    v-if="project.start_date"
                                    class="inline-flex items-center gap-1 text-foreground"
                                >
                                    <Calendar class="size-3 text-muted-foreground" />
                                    {{ fmtShort(project.start_date) }}
                                </span>
                                <button
                                    v-else
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-md border border-dashed border-border px-1.5 py-0.5 text-muted-foreground hover:text-foreground"
                                >
                                    <Calendar class="size-3" />
                                    Start
                                </button>
                                <span class="text-muted-foreground">→</span>
                                <span
                                    v-if="project.target_date"
                                    class="inline-flex items-center gap-1 text-foreground"
                                >
                                    <Flag class="size-3 text-muted-foreground" />
                                    {{ fmtShort(project.target_date) }}
                                </span>
                                <button
                                    v-else
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-md border border-dashed border-border px-1.5 py-0.5 text-muted-foreground hover:text-foreground"
                                >
                                    <Flag class="size-3" />
                                    Target
                                </button>
                            </dd>

                            <!-- Teams -->
                            <dt class="text-[12.5px] text-muted-foreground">Teams</dt>
                            <dd class="flex flex-wrap gap-1">
                                <span
                                    v-for="t in project.teams"
                                    :key="t.id"
                                    class="inline-flex items-center gap-1 rounded-md border border-border bg-card px-1.5 py-px text-[11px] leading-[16px]"
                                >
                                    <Package
                                        class="size-3"
                                        :style="{ color: t.color || '#6366f1' }"
                                    />
                                    <span class="text-foreground">{{ t.key }}</span>
                                </span>
                            </dd>

                            <!-- Labels -->
                            <dt class="text-[12.5px] text-muted-foreground">Labels</dt>
                            <dd>
                                <div v-if="labels.length" class="flex flex-wrap gap-1">
                                    <LabelBadge
                                        v-for="l in labels"
                                        :key="l.id"
                                        :name="l.name"
                                        :color="l.color"
                                    />
                                </div>
                                <button
                                    v-else
                                    type="button"
                                    class="inline-flex items-center gap-1.5 text-[12.5px] text-muted-foreground hover:text-foreground"
                                >
                                    <Plus class="size-3.5" />
                                    Add label
                                </button>
                            </dd>
                        </dl>
                    </section>

                    <!-- ============== MILESTONES ============== -->
                    <section>
                        <header class="mb-2 flex items-center justify-between">
                            <button
                                type="button"
                                class="flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground hover:text-foreground"
                            >
                                Milestones <ChevronDown class="size-3" />
                            </button>
                            <button
                                type="button"
                                class="rounded p-0.5 text-muted-foreground hover:bg-accent hover:text-foreground"
                                aria-label="Add milestone"
                            >
                                <Plus class="size-3" />
                            </button>
                        </header>

                        <ul v-if="project.milestones.length" class="space-y-1.5">
                            <li
                                v-for="ms in project.milestones"
                                :key="ms.id"
                                class="flex items-center gap-2 rounded-md px-1 py-1 hover:bg-accent/40"
                            >
                                <Diamond
                                    class="size-3 shrink-0"
                                    :style="{
                                        color: project.color || '#6366f1',
                                        fill: project.color || '#6366f1',
                                    }"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-[12.5px] font-medium text-foreground">
                                        {{ ms.name }}
                                    </div>
                                    <div class="text-[11px] text-muted-foreground">
                                        {{ ms.percent }}% of {{ ms.issue_count }}
                                    </div>
                                </div>
                                <span
                                    v-if="ms.target_date"
                                    class="shrink-0 rounded-md border border-border bg-card px-1.5 py-px text-[11px] text-muted-foreground tabular-nums"
                                >
                                    {{ fmtShort(ms.target_date) }}
                                </span>
                            </li>
                        </ul>

                        <p v-else class="text-[12.5px] leading-[18px] text-muted-foreground">
                            Add milestones to organize work within your project and break it into more granular stages.
                            <a
                                href="#"
                                class="text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
                                >Learn more</a
                            >
                        </p>
                    </section>

                    <!-- ============== PROGRESS ============== -->
                    <section>
                        <header class="mb-2 flex items-center justify-between">
                            <button
                                type="button"
                                class="flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground hover:text-foreground"
                            >
                                Progress <ChevronDown class="size-3" />
                            </button>
                        </header>

                        <!-- 3 stat cards -->
                        <div class="mb-3 grid grid-cols-3 gap-2">
                            <div class="rounded-md border border-border bg-card p-2">
                                <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                    <span class="size-1.5 rounded-sm bg-zinc-500"></span>
                                    Scope
                                </div>
                                <div class="mt-1 text-[16px] font-semibold tabular-nums">{{ progress.total }}</div>
                            </div>
                            <div class="rounded-md border border-border bg-card p-2">
                                <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                    <span class="size-1.5 rounded-sm bg-amber-400"></span>
                                    Started
                                </div>
                                <div class="mt-1 text-[16px] font-semibold tabular-nums">{{ progress.started }}</div>
                            </div>
                            <div class="rounded-md border border-border bg-card p-2">
                                <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                    <span class="size-1.5 rounded-sm bg-indigo-500"></span>
                                    Done
                                </div>
                                <div class="mt-1 text-[16px] font-semibold tabular-nums">{{ progress.completed }}</div>
                            </div>
                        </div>

                        <!-- Burndown chart -->
                        <div class="relative h-[160px] rounded-md border border-border bg-card">
                            <svg
                                viewBox="0 0 256 140"
                                preserveAspectRatio="none"
                                class="absolute inset-0 h-full w-full"
                                aria-hidden="true"
                            >
                                <!-- Y gridlines -->
                                <line x1="12" y1="18" x2="244" y2="18" stroke="currentColor" stroke-width="0.5" class="text-border" />
                                <line x1="12" y1="70" x2="244" y2="70" stroke="currentColor" stroke-width="0.5" class="text-border" stroke-dasharray="2 3" />
                                <line x1="12" y1="122" x2="244" y2="122" stroke="currentColor" stroke-width="0.5" class="text-border" />
                                <!-- Ideal line -->
                                <polyline
                                    :points="burndownIdealPoints"
                                    fill="none"
                                    stroke="#71717a"
                                    stroke-width="1"
                                    stroke-dasharray="3 3"
                                />
                                <!-- Actual line -->
                                <polyline
                                    :points="burndownActualPoints"
                                    fill="none"
                                    stroke="#6366f1"
                                    stroke-width="1.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                            <div class="pointer-events-none absolute inset-x-2 bottom-1 flex items-center justify-between text-[10px] text-muted-foreground tabular-nums">
                                <span>{{ project.start_date ? fmtShort(project.start_date) : '' }}</span>
                                <span>{{ project.target_date ? fmtShort(project.target_date) : '' }}</span>
                            </div>
                        </div>

                        <!-- Pill tabs -->
                        <div class="mt-3 flex items-center gap-1">
                            <button
                                v-for="t in (['assignees', 'labels', 'cycles'] as const)"
                                :key="t"
                                type="button"
                                @click="progressTab = t"
                                :class="[
                                    'rounded-md px-2 py-1 text-[12px] capitalize transition-colors',
                                    progressTab === t
                                        ? 'bg-accent text-foreground'
                                        : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                                ]"
                            >
                                {{ t }}
                            </button>
                        </div>

                        <!-- Tab body -->
                        <div class="mt-2">
                            <ul v-if="progressTab === 'assignees'" class="space-y-1.5">
                                <li v-if="!assignees.length" class="text-[12.5px] text-muted-foreground">
                                    No assignees yet.
                                </li>
                                <li
                                    v-for="(row, i) in assignees"
                                    :key="row.user?.id ?? `none-${i}`"
                                    class="flex items-center justify-between gap-2"
                                >
                                    <div class="flex min-w-0 items-center gap-1.5">
                                        <Avatar
                                            v-if="row.user"
                                            :name="row.user.name"
                                            :email="row.user.email"
                                            :size="18"
                                        />
                                        <span
                                            v-else
                                            class="size-[18px] rounded-full border border-dashed border-border"
                                        ></span>
                                        <span class="truncate text-[12.5px] text-foreground">
                                            {{ row.user ? row.user.name : 'Unassigned' }}
                                        </span>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1.5">
                                        <span class="text-[11px] text-muted-foreground tabular-nums">
                                            {{ row.percent }}% of {{ row.total }}
                                        </span>
                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                            <circle cx="7" cy="7" r="5" stroke="#3f3f46" stroke-width="1.5" fill="none" />
                                            <circle
                                                cx="7"
                                                cy="7"
                                                r="5"
                                                fill="none"
                                                stroke-width="2"
                                                :stroke="donutStroke(row.percent)"
                                                :stroke-dasharray="`${donutC} ${donutC}`"
                                                :stroke-dashoffset="donutOffset(row.percent)"
                                                transform="rotate(-90 7 7)"
                                            />
                                        </svg>
                                    </div>
                                </li>
                            </ul>
                            <p v-else class="text-[12.5px] text-muted-foreground">Coming soon</p>
                        </div>

                        <!-- Footer % + ring -->
                        <div class="mt-3 flex items-center justify-between gap-2 border-t border-border pt-2">
                            <span class="text-[12px] text-muted-foreground">{{ progress.percent }}% complete</span>
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                <circle cx="7" cy="7" r="5" stroke="#3f3f46" stroke-width="1.5" fill="none" />
                                <circle
                                    cx="7"
                                    cy="7"
                                    r="5"
                                    fill="none"
                                    stroke-width="2"
                                    :stroke="ringStroke"
                                    :stroke-dasharray="`${ringC} ${ringC}`"
                                    :stroke-dashoffset="ringDashOffset"
                                    transform="rotate(-90 7 7)"
                                />
                            </svg>
                        </div>
                    </section>
                </div>
            </aside>
        </div>
    </div>
</template>
