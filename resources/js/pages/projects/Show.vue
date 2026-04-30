<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle2,
    Circle,
    CircleDashed,
    CircleDot,
    CircleSlash,
    Minus,
    PauseCircle,
    SignalHigh,
    SignalLow,
    SignalMedium,
    Calendar,
} from 'lucide-vue-next';

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
type Issue = {
    id: number;
    identifier: string;
    title: string;
    priority: number;
    state: { name: string; type: string; color: string } | null;
    assignee: { id: number; name: string } | null;
    labels: Array<{ id: number; name: string; color: string | null }>;
    updated_at: string | null;
};

defineProps<{
    project: Project;
    issues: Issue[];
}>();

function priorityIcon(p: number) {
    switch (p) {
        case 1: return AlertCircle;
        case 2: return SignalHigh;
        case 3: return SignalMedium;
        case 4: return SignalLow;
        default: return Minus;
    }
}
function priorityClass(p: number) {
    switch (p) {
        case 1: return 'text-rose-500';
        case 2: return 'text-orange-500';
        case 3: return 'text-amber-500';
        case 4: return 'text-zinc-500';
        default: return 'text-zinc-500';
    }
}
function stateIcon(type: string) {
    switch (type) {
        case 'completed': return CheckCircle2;
        case 'started': return CircleDot;
        case 'canceled': return CircleSlash;
        case 'backlog': return CircleDashed;
        default: return Circle;
    }
}
function projectStateIcon(state: string | null) {
    switch (state) {
        case 'completed': return CheckCircle2;
        case 'started': return CircleDot;
        case 'paused': return PauseCircle;
        case 'canceled': return CircleSlash;
        case 'planned': return Calendar;
        default: return CircleDashed;
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
function fmtDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <Head :title="project.name" />

    <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden">
        <header class="flex shrink-0 items-center gap-3 border-b border-border px-5 py-3">
            <Link :href="'/projects'" class="text-muted-foreground hover:text-foreground" aria-label="Back to projects">
                <ArrowLeft class="size-4" />
            </Link>
            <span
                class="flex size-6 items-center justify-center rounded-md text-[10px] font-semibold text-white"
                :style="{ backgroundColor: project.color || '#6366f1' }"
            >
                {{ project.name.charAt(0) }}
            </span>
            <h1 class="truncate text-[13px] font-medium">{{ project.name }}</h1>
        </header>

        <div class="flex min-h-0 flex-1">
            <div class="flex min-w-0 flex-1 flex-col overflow-y-auto">
                <div class="px-8 py-6">
                    <h2 class="text-[20px] font-semibold tracking-tight text-foreground">{{ project.name }}</h2>

                    <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-[13px]">
                        <span class="flex items-center gap-1.5">
                            <component
                                :is="projectStateIcon(project.state)"
                                class="size-3.5 text-muted-foreground"
                            />
                            <span class="capitalize">{{ project.state ?? 'backlog' }}</span>
                        </span>
                        <span v-if="project.lead" class="flex items-center gap-1.5 text-muted-foreground">
                            Lead
                            <span class="flex size-5 items-center justify-center rounded-full bg-muted text-[9px] font-medium text-foreground">
                                {{ initials(project.lead.name) }}
                            </span>
                            <span class="text-foreground">{{ project.lead.name }}</span>
                        </span>
                        <span v-if="project.target_date" class="text-muted-foreground">
                            Target {{ fmtDate(project.target_date) }}
                        </span>
                    </div>

                    <div
                        v-if="project.description"
                        class="prose prose-sm dark:prose-invert mt-6 max-w-none whitespace-pre-wrap text-[14px] leading-relaxed text-foreground/90"
                    >{{ project.description }}</div>

                    <section v-if="project.milestones.length" class="mt-8">
                        <h3 class="mb-3 text-[12px] font-medium uppercase tracking-wide text-muted-foreground">Milestones</h3>
                        <ul class="divide-y divide-border rounded-md border border-border">
                            <li v-for="ms in project.milestones" :key="ms.id" class="px-3 py-2">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-[13px] font-medium">{{ ms.name }}</span>
                                    <span v-if="ms.target_date" class="text-[12px] text-muted-foreground">{{ fmtDate(ms.target_date) }}</span>
                                </div>
                                <p v-if="ms.description" class="mt-1 text-[12.5px] text-muted-foreground">{{ ms.description }}</p>
                            </li>
                        </ul>
                    </section>

                    <section class="mt-8">
                        <h3 class="mb-3 text-[12px] font-medium uppercase tracking-wide text-muted-foreground">
                            Issues
                            <span class="ml-1 text-muted-foreground">{{ issues.length }}</span>
                        </h3>

                        <ul v-if="issues.length" class="divide-y divide-border rounded-md border border-border">
                            <li v-for="issue in issues" :key="issue.id">
                                <Link
                                    :href="`/issues/${issue.identifier}`"
                                    class="flex items-center gap-3 px-3 py-2 hover:bg-accent/50"
                                >
                                    <component
                                        :is="priorityIcon(issue.priority)"
                                        :class="['size-3.5 shrink-0', priorityClass(issue.priority)]"
                                    />
                                    <component
                                        :is="stateIcon(issue.state?.type ?? 'unstarted')"
                                        class="size-3.5 shrink-0"
                                        :style="{ color: issue.state?.color ?? '#94a3b8' }"
                                    />
                                    <span class="font-mono text-[11px] text-muted-foreground">{{ issue.identifier }}</span>
                                    <span class="min-w-0 flex-1 truncate text-[13px]">{{ issue.title }}</span>
                                    <span
                                        v-if="issue.assignee"
                                        class="flex size-5 items-center justify-center rounded-full bg-muted text-[9px] font-medium"
                                        :title="issue.assignee.name"
                                    >
                                        {{ initials(issue.assignee.name) }}
                                    </span>
                                </Link>
                            </li>
                        </ul>
                        <p v-else class="text-[13px] text-muted-foreground">No issues.</p>
                    </section>
                </div>
            </div>

            <aside class="hidden w-[260px] shrink-0 overflow-y-auto border-l border-border bg-muted/20 p-5 lg:block">
                <div class="space-y-5 text-[13px]">
                    <div v-if="project.teams.length">
                        <div class="mb-1.5 text-[11px] uppercase tracking-wide text-muted-foreground">Teams</div>
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="t in project.teams"
                                :key="t.id"
                                class="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-2 py-0.5 text-[11px]"
                            >
                                <span
                                    class="size-1.5 rounded-full"
                                    :style="{ backgroundColor: t.color || '#6366f1' }"
                                ></span>
                                {{ t.key }}
                            </span>
                        </div>
                    </div>

                    <div v-if="project.members.length">
                        <div class="mb-1.5 text-[11px] uppercase tracking-wide text-muted-foreground">Members</div>
                        <ul class="space-y-1.5">
                            <li
                                v-for="m in project.members"
                                :key="m.id"
                                class="flex items-center gap-2 text-foreground"
                            >
                                <span class="flex size-5 items-center justify-center rounded-full bg-muted text-[9px] font-medium">
                                    {{ initials(m.name) }}
                                </span>
                                <span class="truncate">{{ m.name }}</span>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <div class="mb-1.5 text-[11px] uppercase tracking-wide text-muted-foreground">Dates</div>
                        <div class="text-foreground">Start · {{ fmtDate(project.start_date) }}</div>
                        <div class="text-foreground">Target · {{ fmtDate(project.target_date) }}</div>
                        <div v-if="project.completed_at" class="text-foreground">
                            Completed · {{ fmtDate(project.completed_at) }}
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
