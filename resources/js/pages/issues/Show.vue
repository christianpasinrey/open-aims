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
    SignalHigh,
    SignalLow,
    SignalMedium,
} from 'lucide-vue-next';

type State = { id: number; name: string; type: string; color: string };
type Label = { id: number; name: string; color?: string | null };
type User = { id: number; name: string; email: string };
type Issue = {
    id: number;
    identifier: string;
    number: number;
    title: string;
    description: string | null;
    priority: number;
    priority_label: string;
    estimate: number | null;
    due_date: string | null;
    state: State | null;
    assignee: User | null;
    creator: User | null;
    project: { id: number; name: string; slug: string; color: string | null } | null;
    labels: Label[];
    parent: { identifier: string; title: string } | null;
    children: Array<{
        id: number;
        identifier: string;
        title: string;
        priority: number;
        state: { name: string; type: string; color: string } | null;
        assignee: { id: number; name: string } | null;
    }>;
    created_at: string | null;
    updated_at: string | null;
};
type Comment = {
    id: number;
    body: string;
    user: User | null;
    created_at: string | null;
    edited_at: string | null;
};

defineProps<{
    team: { id: number; name: string; key: string; color: string | null };
    issue: Issue;
    comments: Comment[];
    states: State[];
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
function relativeTime(iso: string | null): string {
    if (!iso) return '';
    const d = new Date(iso).getTime();
    const diff = Math.max(0, Date.now() - d);
    const m = Math.floor(diff / 60000);
    if (m < 60) return `${m}m ago`;
    const h = Math.floor(m / 60);
    if (h < 24) return `${h}h ago`;
    const days = Math.floor(h / 24);
    if (days < 30) return `${days}d ago`;
    return fmtDate(iso);
}
</script>

<template>
    <Head :title="`${issue.identifier} — ${issue.title}`" />

    <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden">
        <header
            class="flex shrink-0 items-center gap-3 border-b border-border px-5 py-3"
        >
            <Link
                :href="`/issues?team=${team.key}`"
                class="text-muted-foreground transition-colors hover:text-foreground"
                aria-label="Back to issues"
            >
                <ArrowLeft class="size-4" />
            </Link>
            <span
                class="flex size-6 items-center justify-center rounded-md text-[10px] font-semibold text-white"
                :style="{ backgroundColor: team.color || '#6366f1' }"
            >
                {{ team.key.charAt(0) }}
            </span>
            <span
                class="font-mono text-[12px] text-muted-foreground"
                >{{ issue.identifier }}</span
            >
        </header>

        <div class="flex min-h-0 flex-1">
            <!-- Main column -->
            <div class="flex min-w-0 flex-1 flex-col overflow-y-auto">
                <div class="mx-auto w-full max-w-3xl px-8 py-8">
                    <h1
                        class="text-[22px] font-semibold leading-tight tracking-tight text-foreground"
                    >
                        {{ issue.title }}
                    </h1>

                    <p
                        v-if="issue.parent"
                        class="mt-2 text-[13px] text-muted-foreground"
                    >
                        Sub-issue of
                        <Link
                            :href="`/issues/${issue.parent.identifier}`"
                            class="text-foreground hover:underline"
                        >
                            {{ issue.parent.identifier }} · {{ issue.parent.title }}
                        </Link>
                    </p>

                    <div
                        v-if="issue.description"
                        class="prose prose-sm dark:prose-invert mt-6 max-w-none whitespace-pre-wrap text-[14px] leading-relaxed text-foreground/90"
                    >{{ issue.description }}</div>
                    <p
                        v-else
                        class="mt-6 text-[14px] italic text-muted-foreground"
                    >
                        No description.
                    </p>

                    <!-- Children -->
                    <section v-if="issue.children.length" class="mt-10">
                        <h2 class="mb-3 text-[12px] font-medium uppercase tracking-wide text-muted-foreground">
                            Sub-issues
                        </h2>
                        <ul class="divide-y divide-border rounded-md border border-border">
                            <li v-for="child in issue.children" :key="child.id">
                                <Link
                                    :href="`/issues/${child.identifier}`"
                                    class="flex items-center gap-3 px-3 py-2 hover:bg-accent/50"
                                >
                                    <component
                                        :is="priorityIcon(child.priority)"
                                        :class="['size-3.5 shrink-0', priorityClass(child.priority)]"
                                    />
                                    <component
                                        :is="stateIcon(child.state?.type ?? 'unstarted')"
                                        class="size-3.5 shrink-0"
                                        :style="{ color: child.state?.color ?? '#94a3b8' }"
                                    />
                                    <span class="font-mono text-[11px] text-muted-foreground">{{ child.identifier }}</span>
                                    <span class="min-w-0 flex-1 truncate text-[13px]">{{ child.title }}</span>
                                    <span
                                        v-if="child.assignee"
                                        class="flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-[9px] font-medium"
                                        :title="child.assignee.name"
                                    >
                                        {{ initials(child.assignee.name) }}
                                    </span>
                                </Link>
                            </li>
                        </ul>
                    </section>

                    <!-- Comments -->
                    <section class="mt-10">
                        <h2 class="mb-3 text-[12px] font-medium uppercase tracking-wide text-muted-foreground">
                            Activity
                        </h2>
                        <div v-if="!comments.length" class="text-[13px] text-muted-foreground">
                            No comments yet.
                        </div>
                        <ul v-else class="space-y-4">
                            <li
                                v-for="c in comments"
                                :key="c.id"
                                class="rounded-md border border-border bg-card p-3"
                            >
                                <div class="flex items-center gap-2 text-[12px]">
                                    <span
                                        class="flex size-5 items-center justify-center rounded-full bg-muted text-[9px] font-medium"
                                    >
                                        {{ c.user ? initials(c.user.name) : '?' }}
                                    </span>
                                    <span class="font-medium text-foreground">{{ c.user?.name ?? 'Unknown' }}</span>
                                    <span class="text-muted-foreground">{{ relativeTime(c.created_at) }}</span>
                                </div>
                                <div
                                    class="prose prose-sm dark:prose-invert mt-2 whitespace-pre-wrap text-[13.5px] text-foreground/90"
                                >{{ c.body }}</div>
                            </li>
                        </ul>
                    </section>
                </div>
            </div>

            <!-- Right rail -->
            <aside
                class="hidden w-[300px] shrink-0 overflow-y-auto border-l border-border bg-muted/20 p-5 lg:block"
            >
                <div class="space-y-5 text-[13px]">
                    <div>
                        <div class="mb-1.5 text-[11px] uppercase tracking-wide text-muted-foreground">Status</div>
                        <div class="flex items-center gap-2 text-foreground">
                            <component
                                :is="stateIcon(issue.state?.type ?? 'unstarted')"
                                class="size-3.5"
                                :style="{ color: issue.state?.color ?? '#94a3b8' }"
                            />
                            <span>{{ issue.state?.name ?? '—' }}</span>
                        </div>
                    </div>

                    <div>
                        <div class="mb-1.5 text-[11px] uppercase tracking-wide text-muted-foreground">Priority</div>
                        <div class="flex items-center gap-2 text-foreground">
                            <component
                                :is="priorityIcon(issue.priority)"
                                :class="['size-3.5', priorityClass(issue.priority)]"
                            />
                            <span>{{ issue.priority_label }}</span>
                        </div>
                    </div>

                    <div>
                        <div class="mb-1.5 text-[11px] uppercase tracking-wide text-muted-foreground">Assignee</div>
                        <div v-if="issue.assignee" class="flex items-center gap-2 text-foreground">
                            <span class="flex size-5 items-center justify-center rounded-full bg-muted text-[9px] font-medium">
                                {{ initials(issue.assignee.name) }}
                            </span>
                            <span>{{ issue.assignee.name }}</span>
                        </div>
                        <span v-else class="text-muted-foreground">Unassigned</span>
                    </div>

                    <div>
                        <div class="mb-1.5 text-[11px] uppercase tracking-wide text-muted-foreground">Created by</div>
                        <div v-if="issue.creator" class="flex items-center gap-2 text-foreground">
                            <span class="flex size-5 items-center justify-center rounded-full bg-muted text-[9px] font-medium">
                                {{ initials(issue.creator.name) }}
                            </span>
                            <span>{{ issue.creator.name }}</span>
                        </div>
                        <span v-else class="text-muted-foreground">—</span>
                    </div>

                    <div v-if="issue.project">
                        <div class="mb-1.5 text-[11px] uppercase tracking-wide text-muted-foreground">Project</div>
                        <Link
                            :href="`/projects/${issue.project.slug}`"
                            class="flex items-center gap-2 text-foreground hover:underline"
                        >
                            <span
                                class="size-2 rounded-sm"
                                :style="{ backgroundColor: issue.project.color || '#6366f1' }"
                            ></span>
                            <span>{{ issue.project.name }}</span>
                        </Link>
                    </div>

                    <div v-if="issue.labels.length">
                        <div class="mb-1.5 text-[11px] uppercase tracking-wide text-muted-foreground">Labels</div>
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="label in issue.labels"
                                :key="label.id"
                                class="inline-flex items-center gap-1 rounded-full border border-border bg-card px-2 py-0.5 text-[11px] text-foreground"
                            >
                                <span
                                    class="size-1.5 rounded-full"
                                    :style="{ backgroundColor: label.color || '#94a3b8' }"
                                ></span>
                                {{ label.name }}
                            </span>
                        </div>
                    </div>

                    <div v-if="issue.estimate !== null">
                        <div class="mb-1.5 text-[11px] uppercase tracking-wide text-muted-foreground">Estimate</div>
                        <div class="text-foreground">{{ issue.estimate }} pt</div>
                    </div>

                    <div v-if="issue.due_date">
                        <div class="mb-1.5 text-[11px] uppercase tracking-wide text-muted-foreground">Due</div>
                        <div class="text-foreground">{{ fmtDate(issue.due_date) }}</div>
                    </div>

                    <div class="border-t border-border pt-4 text-[12px] text-muted-foreground">
                        Created {{ relativeTime(issue.created_at) }}<br />
                        Updated {{ relativeTime(issue.updated_at) }}
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
