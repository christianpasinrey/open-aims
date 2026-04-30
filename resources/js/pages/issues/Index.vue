<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowUp,
    Circle,
    CircleDashed,
    CircleDot,
    CircleSlash,
    CheckCircle2,
    Minus,
    SignalHigh,
    SignalLow,
    SignalMedium,
} from 'lucide-vue-next';

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
    return stateOrder.value.map(s => ({
        state: s,
        issues: buckets.get(s.id) ?? [],
    }));
});

const totalIssues = computed(() => props.issues.length);

function priorityIcon(p: number) {
    switch (p) {
        case 1:
            return AlertCircle;
        case 2:
            return SignalHigh;
        case 3:
            return SignalMedium;
        case 4:
            return SignalLow;
        default:
            return Minus;
    }
}
function priorityClass(p: number) {
    switch (p) {
        case 1:
            return 'text-rose-500';
        case 2:
            return 'text-orange-500';
        case 3:
            return 'text-amber-500';
        case 4:
            return 'text-zinc-500';
        default:
            return 'text-zinc-500';
    }
}
function priorityLabel(p: number) {
    return props.priorities[String(p)] ?? 'No priority';
}
function stateIcon(type: string) {
    switch (type) {
        case 'completed':
            return CheckCircle2;
        case 'started':
            return CircleDot;
        case 'canceled':
            return CircleSlash;
        case 'backlog':
            return CircleDashed;
        default:
            return Circle;
    }
}
function initials(name: string) {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map(p => p.charAt(0).toUpperCase())
        .join('');
}
function relativeTime(iso: string | null): string {
    if (!iso) return '';
    const d = new Date(iso).getTime();
    const diff = Math.max(0, Date.now() - d);
    const m = Math.floor(diff / 60000);
    if (m < 60) return `${m}m`;
    const h = Math.floor(m / 60);
    if (h < 24) return `${h}h`;
    const days = Math.floor(h / 24);
    if (days < 30) return `${days}d`;
    const months = Math.floor(days / 30);
    if (months < 12) return `${months}mo`;
    return `${Math.floor(months / 12)}y`;
}
</script>

<template>
    <Head :title="team ? `${team.name} · Issues` : 'Issues'" />

    <div class="flex h-full flex-1 flex-col overflow-hidden">
        <header
            class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-5 py-3"
        >
            <div class="flex items-center gap-2.5">
                <span
                    v-if="team"
                    class="flex size-6 items-center justify-center rounded-md text-[10px] font-semibold text-white"
                    :style="{ backgroundColor: team.color || '#6366f1' }"
                >
                    {{ team.key.charAt(0) }}
                </span>
                <h1 class="text-[13px] font-medium text-foreground">
                    {{ team ? `${team.name} · All issues` : 'All issues' }}
                </h1>
                <span class="text-[12px] text-muted-foreground">{{ totalIssues }}</span>
            </div>
        </header>

        <div
            v-if="!team || totalIssues === 0"
            class="flex flex-1 items-center justify-center px-6 py-12 text-center"
        >
            <div class="max-w-sm">
                <h2 class="text-base font-medium text-foreground">No issues yet</h2>
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

        <div v-else class="flex-1 overflow-y-auto">
            <div
                v-for="group in grouped"
                :key="group.state.id"
                class="border-b border-border last:border-b-0"
            >
                <div
                    class="sticky top-0 z-10 flex items-center gap-2 bg-muted/40 px-5 py-2 backdrop-blur"
                >
                    <component
                        :is="stateIcon(group.state.type)"
                        class="size-3.5"
                        :style="{ color: group.state.color }"
                    />
                    <span class="text-[12px] font-medium text-foreground">{{ group.state.name }}</span>
                    <span class="text-[12px] text-muted-foreground">{{ group.issues.length }}</span>
                </div>

                <ul v-if="group.issues.length" class="divide-y divide-border">
                    <li
                        v-for="issue in group.issues"
                        :key="issue.id"
                        class="group flex items-center gap-3 px-5 py-2 hover:bg-accent/50"
                    >
                        <component
                            :is="priorityIcon(issue.priority)"
                            :class="['size-3.5 shrink-0', priorityClass(issue.priority)]"
                            :title="priorityLabel(issue.priority)"
                        />

                        <component
                            :is="stateIcon(issue.state?.type ?? 'unstarted')"
                            class="size-3.5 shrink-0"
                            :style="{
                                color: issue.state?.color ?? '#94a3b8',
                            }"
                        />

                        <span
                            class="w-[68px] shrink-0 font-mono text-[11px] text-muted-foreground"
                            >{{ issue.identifier }}</span
                        >

                        <span
                            class="min-w-0 flex-1 truncate text-[13px] text-foreground"
                            >{{ issue.title }}</span
                        >

                        <div
                            v-if="issue.labels.length"
                            class="hidden shrink-0 items-center gap-1 lg:flex"
                        >
                            <span
                                v-for="label in issue.labels.slice(0, 3)"
                                :key="label.id"
                                class="inline-flex items-center gap-1 rounded-full border border-border bg-card px-2 py-0.5 text-[10.5px] text-muted-foreground"
                            >
                                <span
                                    class="size-1.5 rounded-full"
                                    :style="{
                                        backgroundColor:
                                            label.color || '#94a3b8',
                                    }"
                                ></span>
                                {{ label.name }}
                            </span>
                        </div>

                        <span
                            v-if="issue.project"
                            class="hidden shrink-0 text-[11px] text-muted-foreground md:inline"
                            :title="issue.project.name"
                        >
                            {{ issue.project.name.length > 28
                                ? issue.project.name.slice(0, 26) + '…'
                                : issue.project.name }}
                        </span>

                        <span
                            class="w-9 shrink-0 text-right text-[11px] text-muted-foreground"
                            >{{ relativeTime(issue.updated_at) }}</span
                        >

                        <span
                            v-if="issue.assignee"
                            class="flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-[10px] font-medium text-foreground"
                            :title="issue.assignee.name"
                        >
                            {{ initials(issue.assignee.name) }}
                        </span>
                        <span
                            v-else
                            class="flex size-6 shrink-0 items-center justify-center rounded-full border border-dashed border-border text-muted-foreground"
                            title="Unassigned"
                        >
                            <Circle class="size-3" />
                        </span>
                    </li>
                </ul>
                <div
                    v-else
                    class="px-5 py-2.5 text-[12px] text-muted-foreground"
                >
                    No issues
                </div>
            </div>
        </div>
    </div>
</template>
