<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    SlidersHorizontal,
    Bell,
    Star,
    LayoutGrid,
    ChevronDown,
    Plus,
} from 'lucide-vue-next';
import StatusIcon from '@/components/repo/StatusIcon.vue';
import PriorityIcon from '@/components/repo/PriorityIcon.vue';
import Avatar from '@/components/repo/Avatar.vue';
import LabelBadge from '@/components/repo/LabelBadge.vue';
import ProjectChip from '@/components/repo/ProjectChip.vue';

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

const props = defineProps<{
    team: Team | null;
    states: State[];
    issues: Issue[];
    priorities: Record<string, string>;
    filters?: { team: string | null; assignee: string | null; state: string | null };
}>();

const stateOrder = computed(() =>
    [...props.states].sort((a, b) => a.position - b.position),
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

const totalIssues = computed(() => props.issues.length);

const headerLabel = computed<string>(() => {
    if (props.filters?.assignee === 'me') return 'My Issues';
    if (props.filters?.assignee === 'unassigned') return 'Unassigned';
    return 'Issues';
});

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
    const s = props.filters?.state;
    if (s === 'started') return 'active';
    if (s === 'backlog') return 'backlog';
    return null;
});

function relativeTime(iso: string | null): string {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}
</script>

<template>
    <Head :title="team ? `${team.name} · ${headerLabel}` : headerLabel" />

    <div class="flex h-full flex-1 flex-col overflow-hidden">
        <!-- Top bar: title + actions -->
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
                <h1 class="text-[13px] font-medium text-foreground">{{ headerLabel }}</h1>
                <button
                    type="button"
                    class="text-muted-foreground transition-colors hover:text-foreground"
                    aria-label="Favourite"
                >
                    <Star class="size-3.5" />
                </button>
            </div>
            <button
                type="button"
                class="text-muted-foreground transition-colors hover:text-foreground"
                aria-label="Notifications"
            >
                <Bell class="size-3.5" />
            </button>
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
                <button
                    type="button"
                    class="rounded-md p-1.5 transition-colors hover:bg-accent hover:text-foreground"
                    aria-label="Filter"
                >
                    <SlidersHorizontal class="size-3.5" />
                </button>
                <button
                    type="button"
                    class="rounded-md p-1.5 transition-colors hover:bg-accent hover:text-foreground"
                    aria-label="Display"
                >
                    <LayoutGrid class="size-3.5" />
                </button>
            </div>
        </div>

        <!-- Empty -->
        <div
            v-if="!team || totalIssues === 0"
            class="flex flex-1 items-center justify-center px-6 py-12 text-center"
        >
            <div class="max-w-sm">
                <h2 class="text-base font-medium text-foreground">No issues yet</h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Run
                    <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-[12px]"
                        >php artisan aims:import-snapshot</code
                    >
                    to populate the workspace.
                </p>
            </div>
        </div>

        <!-- Grouped list -->
        <div v-else class="flex-1 overflow-y-auto">
            <section
                v-for="group in grouped"
                :key="group.state.id"
            >
                <div
                    class="sticky top-0 z-10 flex items-center gap-2 bg-muted/40 px-4 py-1.5 backdrop-blur"
                >
                    <ChevronDown class="size-3 text-muted-foreground" />
                    <StatusIcon :type="group.state.type" :color="group.state.color" :size="14" />
                    <span class="text-[12.5px] font-medium text-foreground">{{ group.state.name }}</span>
                    <span class="text-[12px] text-muted-foreground">{{ group.issues.length }}</span>
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

                            <span class="min-w-0 truncate text-[13px] text-foreground">{{ issue.title }}</span>

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

                            <span class="text-right text-[11px] text-muted-foreground tabular-nums">
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
