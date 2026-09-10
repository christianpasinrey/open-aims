<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Calendar,
    Check,
    ChevronDown,
    ChevronRight,
    ChevronsUpDown,
    Diamond,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Avatar from '@/components/repo/Avatar.vue';
import LabelBadge from '@/components/repo/LabelBadge.vue';
import PriorityIcon from '@/components/repo/PriorityIcon.vue';
import ProjectIcon from '@/components/repo/ProjectIcon.vue';
import StatusIcon from '@/components/repo/StatusIcon.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { startedProgressByState } from '@/lib/states';

type Team = { id: number; name: string; key: string; color: string | null };
type State = {
    id: number;
    name: string;
    type: string;
    color: string;
    position: number;
};
type Issue = {
    id: number;
    identifier: string;
    title: string;
    priority: number;
    state_name: string | null;
    state: { name: string; type: string; color: string } | null;
    assignee: { id: number; name: string } | null;
    labels: Array<{ id: number; name: string; color?: string | null }>;
    updated_at: string | null;
};
type MilestoneStatus = 'completed' | 'overdue' | 'on_track' | 'unscheduled';

const props = defineProps<{
    project: {
        id: number;
        name: string;
        slug: string;
        color: string | null;
        icon: string | null;
        teams: Team[];
    };
    milestone: {
        id: number;
        name: string;
        description: string | null;
        target_date: string | null;
        completed_at: string | null;
        status: MilestoneStatus;
        days_left: number | null;
    };
    progress: {
        total: number;
        completed: number;
        started: number;
        percent: number;
    };
    state_breakdown: Array<{
        name: string;
        type: string;
        color: string | null;
        position: number;
        count: number;
    }>;
    assignees: Array<{
        user: { id: number; name: string; email: string } | null;
        total: number;
        completed: number;
        percent: number;
    }>;
    states: State[];
    milestones: Array<{
        id: number;
        name: string;
        target_date: string | null;
        completed_at: string | null;
        percent: number;
    }>;
    issues: Issue[];
}>();

const accent = computed(() => props.project.color || '#6366f1');
const team = computed(() => props.project.teams[0] ?? null);
const projectMilestonesHref = computed(
    () => `/projects/${props.project.slug}?tab=milestones`,
);
const startedProgress = computed(() => startedProgressByState(props.states));

const STATUS_LABEL: Record<MilestoneStatus, string> = {
    completed: 'Completed',
    overdue: 'Overdue',
    on_track: 'In progress',
    unscheduled: 'No target date',
};
const STATUS_CLASS: Record<MilestoneStatus, string> = {
    completed: 'text-emerald-500',
    overdue: 'text-rose-400',
    on_track: 'text-foreground',
    unscheduled: 'text-muted-foreground',
};

function fmtDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function fmtShort(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

const scheduleNote = computed(() => {
    const days = props.milestone.days_left;

    if (props.milestone.status === 'completed' || days === null) {
        return null;
    }

    if (days === 0) {
        return 'Due today';
    }

    return days > 0
        ? `${days} day${days === 1 ? '' : 's'} left`
        : `${Math.abs(days)} day${days === -1 ? '' : 's'} late`;
});

const grouped = computed(() => {
    const buckets = new Map<string, Issue[]>();

    for (const issue of props.issues) {
        const key = issue.state_name ?? '—';

        if (!buckets.has(key)) {
            buckets.set(key, []);
        }

        buckets.get(key)!.push(issue);
    }

    const known = props.states.filter((s) => buckets.has(s.name));
    const unknown = [...buckets.keys()].filter(
        (name) => !props.states.some((s) => s.name === name),
    );

    return [
        ...known.map((state) => ({
            key: state.name,
            state: {
                id: state.id as number | null,
                name: state.name,
                type: state.type,
                color: state.color,
            },
            issues: buckets.get(state.name)!,
        })),
        ...unknown.map((name) => ({
            key: name,
            state: {
                id: null,
                name,
                type: buckets.get(name)![0]?.state?.type ?? 'unstarted',
                color: buckets.get(name)![0]?.state?.color ?? '#94a3b8',
            },
            issues: buckets.get(name)!,
        })),
    ];
});

const collapsed = ref<Set<string>>(new Set());

function toggleGroup(key: string) {
    const next = new Set(collapsed.value);

    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }

    collapsed.value = next;
}
</script>

<template>
    <Head :title="`${milestone.name} · ${project.name}`" />

    <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden">
        <header
            class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4 py-2.5"
        >
            <nav
                class="flex min-w-0 items-center gap-2 text-[12.5px]"
                aria-label="Breadcrumb"
            >
                <Link
                    v-if="team"
                    :href="`/projects?team=${team.key}`"
                    class="hidden shrink-0 items-center gap-1.5 text-muted-foreground transition-colors hover:text-foreground sm:flex"
                >
                    <span
                        class="flex size-4 items-center justify-center rounded-md text-[9px] font-semibold text-white"
                        :style="{ backgroundColor: team.color || '#6366f1' }"
                    >
                        {{ team.key.charAt(0) }}
                    </span>
                    <span>{{ team.name }}</span>
                </Link>
                <span v-if="team" class="hidden text-muted-foreground sm:inline"
                    >›</span
                >
                <Link
                    :href="projectMilestonesHref"
                    class="flex min-w-0 items-center gap-1.5 text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ProjectIcon
                        :icon="project.icon"
                        :color="project.color"
                        :size="14"
                        rounded="sm"
                    />
                    <span class="truncate">{{ project.name }}</span>
                </Link>
                <span class="text-muted-foreground">›</span>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="flex min-w-0 items-center gap-1.5 rounded-md px-1 py-0.5 text-foreground transition-colors hover:bg-accent"
                        >
                            <Diamond
                                class="size-3 shrink-0"
                                :style="{ color: accent, fill: accent }"
                            />
                            <h1 class="truncate">{{ milestone.name }}</h1>
                            <ChevronsUpDown
                                class="size-3 shrink-0 text-muted-foreground"
                            />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-64">
                        <DropdownMenuItem
                            v-for="ms in milestones"
                            :key="ms.id"
                            as-child
                        >
                            <Link
                                :href="`/projects/${project.slug}/milestones/${ms.id}`"
                                class="flex w-full items-center gap-2"
                            >
                                <Check
                                    class="size-3.5 shrink-0"
                                    :class="
                                        ms.id === milestone.id
                                            ? 'opacity-100'
                                            : 'opacity-0'
                                    "
                                />
                                <span class="min-w-0 flex-1 truncate">{{
                                    ms.name
                                }}</span>
                                <span
                                    class="text-[11px] text-muted-foreground tabular-nums"
                                    >{{ ms.percent }}%</span
                                >
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </nav>
        </header>

        <div class="flex min-h-0 flex-1 flex-col overflow-y-auto lg:flex-row">
            <main class="min-w-0 flex-1 lg:overflow-y-auto">
                <section
                    class="mx-auto w-full max-w-3xl px-4 pt-6 pb-4 sm:px-8 sm:pt-8"
                >
                    <div class="flex items-center gap-2">
                        <Diamond
                            class="size-4 shrink-0"
                            :style="{
                                color: accent,
                                fill:
                                    milestone.status === 'completed'
                                        ? accent
                                        : 'transparent',
                            }"
                        />
                        <h2
                            class="text-[22px] font-semibold tracking-tight text-balance text-foreground"
                        >
                            {{ milestone.name }}
                        </h2>
                    </div>
                    <p
                        v-if="milestone.description"
                        class="mt-2 text-[13.5px] leading-relaxed whitespace-pre-line text-muted-foreground"
                    >
                        {{ milestone.description }}
                    </p>

                    <div class="mt-5">
                        <div
                            class="flex items-baseline justify-between gap-3 text-[12.5px]"
                        >
                            <span class="text-muted-foreground">
                                <span
                                    class="font-medium text-foreground tabular-nums"
                                    >{{ progress.completed }}</span
                                >
                                of
                                <span class="tabular-nums">{{
                                    progress.total
                                }}</span>
                                issues done
                            </span>
                            <span
                                class="font-medium text-foreground tabular-nums"
                                >{{ progress.percent }}%</span
                            >
                        </div>
                        <div
                            class="mt-1.5 h-2 overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            :aria-valuenow="progress.percent"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-label="Milestone progress"
                        >
                            <div
                                class="h-full rounded-full transition-[width]"
                                :style="{
                                    width: `${progress.percent}%`,
                                    backgroundColor: accent,
                                }"
                            ></div>
                        </div>
                    </div>
                </section>

                <div
                    v-if="!issues.length"
                    class="mx-auto w-full max-w-3xl px-4 pb-10 sm:px-8"
                >
                    <div
                        class="rounded-md border border-dashed border-border px-6 py-10 text-center text-[13px] text-muted-foreground"
                    >
                        No issues in this milestone yet. Set the milestone from
                        an issue's properties, or pass
                        <code class="font-mono text-[12px]">milestone</code> to
                        issues-update over MCP.
                    </div>
                </div>

                <section
                    v-for="group in grouped"
                    :key="group.key"
                    class="border-t border-border first-of-type:border-t-0"
                >
                    <button
                        type="button"
                        class="sticky top-0 z-10 flex w-full items-center gap-2 bg-muted/40 px-4 py-2 text-left backdrop-blur transition-colors hover:bg-muted/60"
                        :aria-expanded="!collapsed.has(group.key)"
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
                        <StatusIcon
                            :type="group.state.type"
                            :color="group.state.color"
                            :progress="
                                group.state.id != null
                                    ? startedProgress[group.state.id]
                                    : undefined
                            "
                        />
                        <span
                            class="text-[12.5px] font-medium text-foreground"
                            >{{ group.state.name }}</span
                        >
                        <span
                            class="text-[12px] text-muted-foreground tabular-nums"
                            >{{ group.issues.length }}</span
                        >
                    </button>
                    <ul
                        v-show="!collapsed.has(group.key)"
                        class="divide-y divide-border"
                    >
                        <li v-for="issue in group.issues" :key="issue.id">
                            <Link
                                :href="`/issues/${issue.identifier}`"
                                class="flex items-center gap-2 px-4 py-2 hover:bg-accent/40"
                            >
                                <PriorityIcon :priority="issue.priority" />
                                <span
                                    class="hidden w-16 shrink-0 font-mono text-[11px] text-muted-foreground tabular-nums sm:inline"
                                    >{{ issue.identifier }}</span
                                >
                                <StatusIcon
                                    :type="issue.state?.type ?? 'unstarted'"
                                    :color="issue.state?.color"
                                />
                                <span
                                    class="min-w-0 flex-1 truncate text-[13px]"
                                    >{{ issue.title }}</span
                                >
                                <span class="hidden items-center gap-1 lg:flex">
                                    <LabelBadge
                                        v-for="label in issue.labels.slice(
                                            0,
                                            2,
                                        )"
                                        :key="label.id"
                                        :name="label.name"
                                        :color="label.color"
                                    />
                                </span>
                                <span
                                    class="hidden w-12 shrink-0 text-right text-[11px] text-muted-foreground tabular-nums sm:inline"
                                    >{{ fmtShort(issue.updated_at) }}</span
                                >
                                <Avatar
                                    v-if="issue.assignee"
                                    :name="issue.assignee.name"
                                    :size="20"
                                />
                                <span
                                    v-else
                                    class="size-5 shrink-0 rounded-full border border-dashed border-border"
                                ></span>
                            </Link>
                        </li>
                    </ul>
                </section>
            </main>

            <aside
                class="w-full shrink-0 space-y-2 border-t border-border bg-background/40 px-3 py-3 text-[13px] lg:w-[300px] lg:overflow-y-auto lg:border-t-0 lg:border-l"
            >
                <section
                    class="rounded-lg border border-border/60 bg-card/40 px-3 py-2.5"
                >
                    <h3
                        class="mb-2 text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Properties
                    </h3>
                    <dl
                        class="grid grid-cols-[90px_1fr] items-center gap-x-3 gap-y-2"
                    >
                        <dt class="text-[12px] text-muted-foreground">
                            Status
                        </dt>
                        <dd :class="STATUS_CLASS[milestone.status]">
                            {{ STATUS_LABEL[milestone.status] }}
                        </dd>
                        <dt class="text-[12px] text-muted-foreground">
                            Target
                        </dt>
                        <dd class="flex items-center gap-1.5 tabular-nums">
                            <Calendar class="size-3.5 text-muted-foreground" />
                            {{ fmtDate(milestone.target_date) }}
                            <span
                                v-if="scheduleNote"
                                class="text-[11.5px]"
                                :class="
                                    milestone.status === 'overdue'
                                        ? 'text-rose-400'
                                        : 'text-muted-foreground'
                                "
                                >· {{ scheduleNote }}</span
                            >
                        </dd>
                        <template v-if="milestone.completed_at">
                            <dt class="text-[12px] text-muted-foreground">
                                Completed
                            </dt>
                            <dd class="tabular-nums">
                                {{ fmtDate(milestone.completed_at) }}
                            </dd>
                        </template>
                    </dl>
                </section>

                <section
                    class="rounded-lg border border-border/60 bg-card/40 px-3 py-2.5"
                >
                    <h3
                        class="mb-2 text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        By status
                    </h3>
                    <ul v-if="state_breakdown.length" class="space-y-1.5">
                        <li
                            v-for="row in state_breakdown"
                            :key="row.name"
                            class="flex items-center gap-2"
                        >
                            <StatusIcon :type="row.type" :color="row.color" />
                            <span class="min-w-0 flex-1 truncate">{{
                                row.name
                            }}</span>
                            <span
                                class="text-[12px] text-muted-foreground tabular-nums"
                                >{{ row.count }}</span
                            >
                        </li>
                    </ul>
                    <p v-else class="text-[12.5px] text-muted-foreground">
                        No issues yet.
                    </p>
                </section>

                <section
                    v-if="assignees.length"
                    class="rounded-lg border border-border/60 bg-card/40 px-3 py-2.5"
                >
                    <h3
                        class="mb-2 text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Assignees
                    </h3>
                    <ul class="space-y-1.5">
                        <li
                            v-for="row in assignees"
                            :key="row.user?.id ?? 0"
                            class="flex items-center gap-2"
                        >
                            <Avatar
                                v-if="row.user"
                                :name="row.user.name"
                                :email="row.user.email"
                                :size="18"
                            />
                            <span
                                v-else
                                class="size-[18px] shrink-0 rounded-full border border-dashed border-border"
                            ></span>
                            <span class="min-w-0 flex-1 truncate">{{
                                row.user?.name ?? 'Unassigned'
                            }}</span>
                            <span
                                class="text-[12px] text-muted-foreground tabular-nums"
                                >{{ row.completed }}/{{ row.total }}</span
                            >
                        </li>
                    </ul>
                </section>
            </aside>
        </div>
    </div>
</template>
