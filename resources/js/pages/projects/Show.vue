<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Box,
    Calendar,
    ChevronDown,
    Plus,
    SlidersHorizontal,
    LayoutGrid,
    Star,
    Bell,
    MoreHorizontal,
    Link as LinkIcon,
} from 'lucide-vue-next';
import StatusIcon from '@/components/repo/StatusIcon.vue';
import PriorityIcon from '@/components/repo/PriorityIcon.vue';
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
    milestones: Array<{ id: number; name: string; description: string | null; target_date: string | null }>;
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

const props = defineProps<{
    project: Project;
    issues: Issue[];
    states: State[];
    progress: { total: number; completed: number; percent: number };
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

function projectStateLabel() {
    return props.project.state ?? 'backlog';
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
                <Box
                    class="size-3.5 shrink-0"
                    :style="{ color: project.color || '#a1a1aa' }"
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
                    <Box
                        class="mb-4 size-8"
                        :style="{ color: project.color || '#a1a1aa' }"
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
                            <StatusIcon
                                :type="project.state === 'started' ? 'started' : project.state === 'completed' ? 'completed' : 'backlog'"
                            />
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
                <div class="space-y-5 text-[13px]">
                    <div>
                        <div class="mb-2 flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                            Properties <ChevronDown class="size-3" />
                        </div>
                        <dl class="space-y-2 text-[13px]">
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-muted-foreground">Status</dt>
                                <dd class="flex items-center gap-1.5 text-foreground">
                                    <StatusIcon
                                        :type="project.state === 'started' ? 'started' : project.state === 'completed' ? 'completed' : 'backlog'"
                                    />
                                    <span class="capitalize">{{ projectStateLabel() }}</span>
                                </dd>
                            </div>
                            <div v-if="project.lead" class="flex items-center justify-between gap-2">
                                <dt class="text-muted-foreground">Lead</dt>
                                <dd class="flex items-center gap-1.5 text-foreground">
                                    <Avatar :name="project.lead.name" :email="project.lead.email" :size="16" />
                                    <span class="truncate">{{ project.lead.name }}</span>
                                </dd>
                            </div>
                            <div v-if="project.members.length" class="flex items-start justify-between gap-2">
                                <dt class="text-muted-foreground">Members</dt>
                                <dd class="flex flex-wrap items-center justify-end gap-1">
                                    <Avatar
                                        v-for="m in project.members.slice(0, 4)"
                                        :key="m.id"
                                        :name="m.name"
                                        :email="m.email"
                                        :size="18"
                                    />
                                </dd>
                            </div>
                            <div v-if="project.target_date" class="flex items-center justify-between gap-2">
                                <dt class="text-muted-foreground">Target</dt>
                                <dd class="text-foreground">{{ fmtDate(project.target_date) }}</dd>
                            </div>
                            <div v-if="project.start_date" class="flex items-center justify-between gap-2">
                                <dt class="text-muted-foreground">Start</dt>
                                <dd class="text-foreground">{{ fmtDate(project.start_date) }}</dd>
                            </div>
                            <div v-if="project.teams.length" class="flex items-center justify-between gap-2">
                                <dt class="text-muted-foreground">Teams</dt>
                                <dd class="flex flex-wrap justify-end gap-1">
                                    <span
                                        v-for="t in project.teams"
                                        :key="t.id"
                                        class="inline-flex items-center gap-1 rounded-md border border-border bg-card px-1.5 py-px text-[11px]"
                                    >
                                        <span
                                            class="size-1.5 rounded-full"
                                            :style="{ backgroundColor: t.color || '#6366f1' }"
                                        ></span>
                                        {{ t.key }}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div>
                        <div class="mb-2 flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                            Progress <ChevronDown class="size-3" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-md border border-border bg-card p-2">
                                <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                    <span class="size-1.5 rounded-sm bg-zinc-500"></span>
                                    Scope
                                </div>
                                <div class="mt-1 text-[18px] font-semibold tabular-nums">{{ progress.total }}</div>
                            </div>
                            <div class="rounded-md border border-border bg-card p-2">
                                <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                    <span class="size-1.5 rounded-sm bg-emerald-500"></span>
                                    Completed
                                </div>
                                <div class="mt-1 text-[18px] font-semibold tabular-nums">{{ progress.completed }}</div>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-2">
                            <span class="text-[12px] text-muted-foreground">{{ progress.percent }}%</span>
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
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
