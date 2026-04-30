<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Calendar,
    ChevronDown,
    Copy,
    GitBranch,
    Link as LinkIcon,
    Network,
    Play,
    Plus,
} from 'lucide-vue-next';
import StatusIcon from '@/components/repo/StatusIcon.vue';
import PriorityIcon from '@/components/repo/PriorityIcon.vue';
import Avatar from '@/components/repo/Avatar.vue';
import LabelBadge from '@/components/repo/LabelBadge.vue';
import ProjectChip from '@/components/repo/ProjectChip.vue';
import { renderMarkdown } from '@/lib/markdown';

type State = { id: number; name: string; type: string; color: string };
type Label = { id: number; name: string; color?: string | null };
type User = { id: number; name: string; email: string };
type Cycle = {
    id: number;
    number: number;
    name: string | null;
    starts_at: string | null;
    ends_at: string | null;
};
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
    project: {
        id: number;
        name: string;
        slug: string;
        color: string | null;
        icon: string | null;
    } | null;
    cycle: Cycle | null;
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
    completed_at?: string | null;
    canceled_at?: string | null;
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

const props = defineProps<{
    team: { id: number; name: string; key: string; color: string | null };
    issue: Issue;
    comments: Comment[];
    states: State[];
}>();

const descriptionHtml = computed<string>(() =>
    renderMarkdown(props.issue.description),
);

const commentBodies = computed<Record<number, string>>(() =>
    Object.fromEntries(props.comments.map((c) => [c.id, renderMarkdown(c.body)])),
);

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

// Strip time from a date and return midnight ms.
function dayMs(iso: string): number {
    const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
    return new Date(y!, (m ?? 1) - 1, d ?? 1).getTime();
}

const dueInfo = computed<{ label: string; isOverdue: boolean } | null>(() => {
    const iso = props.issue.due_date;
    if (!iso) return null;
    const target = dayMs(iso);
    const now = new Date();
    const today = new Date(
        now.getFullYear(),
        now.getMonth(),
        now.getDate(),
    ).getTime();
    const diffDays = Math.round((target - today) / 86_400_000);
    let label: string;
    if (diffDays === 0) label = 'Today';
    else if (diffDays === 1) label = 'Tomorrow';
    else if (diffDays === -1) label = 'Yesterday';
    else if (diffDays > 1 && diffDays < 7) {
        label = new Date(target).toLocaleDateString(undefined, {
            weekday: 'long',
        });
    } else {
        const sameYear =
            new Date(target).getFullYear() === now.getFullYear();
        label = new Date(target).toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
            ...(sameYear ? {} : { year: 'numeric' }),
        });
    }
    const isCompleted =
        props.issue.state?.type === 'completed' ||
        props.issue.state?.type === 'canceled';
    return { label, isOverdue: diffDays < 0 && !isCompleted };
});
</script>

<template>
    <Head :title="`${issue.identifier} — ${issue.title}`" />

    <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden">
        <header
            class="flex shrink-0 items-center gap-3 border-b border-border px-4 py-2.5"
        >
            <Link
                :href="`/issues?team=${team.key}`"
                class="text-muted-foreground transition-colors hover:text-foreground"
                aria-label="Back to issues"
            >
                <ArrowLeft class="size-4" />
            </Link>
            <span
                class="flex size-5 items-center justify-center rounded-md text-[10px] font-semibold text-white"
                :style="{ backgroundColor: team.color || '#6366f1' }"
            >
                {{ team.key.charAt(0) }}
            </span>
            <span class="font-mono text-[12px] text-muted-foreground"
                >{{ issue.identifier }}</span
            >

            <div class="ml-auto flex items-center gap-0.5 text-muted-foreground">
                <button
                    type="button"
                    class="inline-flex size-7 items-center justify-center rounded-md transition-colors hover:bg-accent hover:text-foreground"
                    aria-label="Copy link"
                >
                    <LinkIcon class="size-4" />
                </button>
                <button
                    type="button"
                    class="inline-flex size-7 items-center justify-center rounded-md transition-colors hover:bg-accent hover:text-foreground"
                    aria-label="Copy ID"
                >
                    <Copy class="size-4" />
                </button>
                <button
                    type="button"
                    class="inline-flex size-7 items-center justify-center rounded-md transition-colors hover:bg-accent hover:text-foreground"
                    aria-label="Branch"
                >
                    <GitBranch class="size-4" />
                </button>
                <button
                    type="button"
                    class="inline-flex size-7 items-center justify-center rounded-md transition-colors hover:bg-accent hover:text-foreground"
                    aria-label="Relations"
                >
                    <Network class="size-4" />
                </button>
                <button
                    type="button"
                    class="ml-1 inline-flex size-7 items-center justify-center rounded-md transition-colors hover:bg-accent hover:text-foreground"
                    aria-label="More"
                >
                    <ChevronDown class="size-4" />
                </button>
            </div>
        </header>

        <div class="flex min-h-0 flex-1">
            <div class="flex min-w-0 flex-1 flex-col overflow-y-auto">
                <div class="mx-auto w-full max-w-3xl px-8 py-8">
                    <h1
                        class="text-[22px] font-semibold leading-tight tracking-tight text-foreground"
                    >
                        {{ issue.title }}
                    </h1>

                    <div
                        v-if="descriptionHtml"
                        class="markdown-body mt-6"
                        v-html="descriptionHtml"
                    ></div>
                    <p
                        v-else
                        class="mt-6 text-[14px] italic text-muted-foreground"
                    >
                        No description.
                    </p>

                    <section class="mt-10">
                        <h2
                            class="mb-3 text-[12px] font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Activity
                        </h2>
                        <div
                            v-if="!comments.length"
                            class="text-[13px] text-muted-foreground"
                        >
                            No comments yet.
                        </div>
                        <ul v-else class="space-y-4">
                            <li
                                v-for="c in comments"
                                :key="c.id"
                                class="rounded-md border border-border bg-card p-3"
                            >
                                <div class="flex items-center gap-2 text-[12px]">
                                    <Avatar
                                        v-if="c.user"
                                        :name="c.user.name"
                                        :email="c.user.email"
                                        :size="20"
                                    />
                                    <span class="font-medium text-foreground">{{
                                        c.user?.name ?? 'Unknown'
                                    }}</span>
                                    <span class="text-muted-foreground">{{
                                        relativeTime(c.created_at)
                                    }}</span>
                                </div>
                                <div
                                    class="markdown-body mt-2"
                                    v-html="commentBodies[c.id]"
                                ></div>
                            </li>
                        </ul>
                    </section>
                </div>
            </div>

            <aside
                class="hidden w-[280px] shrink-0 overflow-y-auto border-l border-border bg-muted/20 px-5 py-5 lg:block"
            >
                <div class="space-y-5">
                    <!-- Properties -->
                    <section>
                        <button
                            type="button"
                            class="flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground hover:text-foreground"
                        >
                            <span>Properties</span>
                            <ChevronDown class="size-3" />
                        </button>
                        <div class="mt-2 flex flex-col gap-1.5">
                            <!-- Status -->
                            <button
                                type="button"
                                class="flex items-center gap-2 rounded px-1 py-1 text-left text-[13px] text-foreground hover:bg-accent/60"
                            >
                                <template v-if="issue.state">
                                    <StatusIcon
                                        :type="issue.state.type"
                                        :color="issue.state.color"
                                    />
                                    <span>{{ issue.state.name }}</span>
                                </template>
                                <template v-else>
                                    <span
                                        class="size-3.5 rounded-full border border-dashed border-border"
                                    ></span>
                                    <span class="text-muted-foreground">—</span>
                                </template>
                            </button>

                            <!-- Priority -->
                            <button
                                type="button"
                                class="flex items-center gap-2 rounded px-1 py-1 text-left text-[13px] text-foreground hover:bg-accent/60"
                            >
                                <PriorityIcon :priority="issue.priority" :size="14" />
                                <span>{{ issue.priority_label }}</span>
                            </button>

                            <!-- Assignee -->
                            <button
                                type="button"
                                class="flex items-center gap-2 rounded px-1 py-1 text-left text-[13px] text-foreground hover:bg-accent/60"
                            >
                                <template v-if="issue.assignee">
                                    <Avatar
                                        :name="issue.assignee.name"
                                        :email="issue.assignee.email"
                                        :size="18"
                                    />
                                    <span>{{ issue.assignee.name }}</span>
                                </template>
                                <template v-else>
                                    <span
                                        class="size-3.5 rounded-full border border-dashed border-border"
                                    ></span>
                                    <span class="text-muted-foreground">Unassigned</span>
                                </template>
                            </button>

                            <!-- Cycle -->
                            <button
                                type="button"
                                class="flex items-center gap-2 rounded px-1 py-1 text-left text-[13px] text-foreground hover:bg-accent/60"
                            >
                                <template v-if="issue.cycle">
                                    <Play
                                        class="size-3.5 shrink-0 fill-indigo-400 text-indigo-400"
                                    />
                                    <span>Cycle {{ issue.cycle.number }}</span>
                                </template>
                                <template v-else>
                                    <span
                                        class="size-3.5 rounded-full border border-dashed border-border"
                                    ></span>
                                    <span class="text-muted-foreground">No cycle</span>
                                </template>
                            </button>

                            <!-- Due date -->
                            <button
                                v-if="dueInfo"
                                type="button"
                                class="flex items-center gap-2 rounded px-1 py-1 text-left text-[13px] hover:bg-accent/60"
                                :class="
                                    dueInfo.isOverdue
                                        ? 'text-red-400'
                                        : 'text-foreground'
                                "
                            >
                                <Calendar
                                    class="size-3.5 shrink-0"
                                    :class="
                                        dueInfo.isOverdue
                                            ? 'text-red-400'
                                            : 'text-muted-foreground'
                                    "
                                />
                                <span>{{ dueInfo.label }}</span>
                            </button>
                        </div>
                    </section>

                    <!-- Labels -->
                    <section>
                        <button
                            type="button"
                            class="flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground hover:text-foreground"
                        >
                            <span>Labels</span>
                            <ChevronDown class="size-3" />
                        </button>
                        <div class="mt-2">
                            <template v-if="issue.labels.length">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <LabelBadge
                                        v-for="label in issue.labels"
                                        :key="label.id"
                                        :name="label.name"
                                        :color="label.color"
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex size-[18px] items-center justify-center rounded-full border border-dashed border-border text-muted-foreground transition-colors hover:border-foreground hover:text-foreground"
                                        aria-label="Add label"
                                    >
                                        <Plus class="size-3" />
                                    </button>
                                </div>
                            </template>
                            <button
                                v-else
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-full border border-dashed border-border px-2 py-px text-[11px] leading-[16px] text-muted-foreground transition-colors hover:border-foreground hover:text-foreground"
                            >
                                <Plus class="size-3" />
                                <span>Add label</span>
                            </button>
                        </div>
                    </section>

                    <!-- Project -->
                    <section>
                        <button
                            type="button"
                            class="flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground hover:text-foreground"
                        >
                            <span>Project</span>
                            <ChevronDown class="size-3" />
                        </button>
                        <div class="mt-2">
                            <ProjectChip
                                v-if="issue.project"
                                :name="issue.project.name"
                                :color="issue.project.color"
                                :icon="issue.project.icon"
                                :slug="issue.project.slug"
                                :href="`/projects/${issue.project.slug}`"
                            />
                            <button
                                v-else
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-md border border-dashed border-border px-2 py-1 text-[12px] text-muted-foreground transition-colors hover:border-foreground hover:text-foreground"
                            >
                                <Plus class="size-3" />
                                <span>Add to project</span>
                            </button>
                        </div>
                    </section>

                    <!-- Relations -->
                    <section v-if="issue.parent || issue.children.length">
                        <button
                            type="button"
                            class="flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground hover:text-foreground"
                        >
                            <span>Relations</span>
                            <ChevronDown class="size-3" />
                        </button>

                        <div class="mt-2 space-y-3">
                            <div v-if="issue.parent">
                                <div
                                    class="mb-1 text-[10px] font-medium uppercase tracking-wide text-muted-foreground"
                                >
                                    Sub-issue of
                                </div>
                                <Link
                                    :href="`/issues/${issue.parent.identifier}`"
                                    class="flex items-center gap-2 rounded px-1 py-1 text-[13px] text-foreground hover:bg-accent/60"
                                >
                                    <StatusIcon type="unstarted" />
                                    <span
                                        class="font-mono text-[11px] text-muted-foreground"
                                        >{{ issue.parent.identifier }}</span
                                    >
                                    <span class="min-w-0 flex-1 truncate">{{
                                        issue.parent.title
                                    }}</span>
                                </Link>
                            </div>

                            <div v-if="issue.children.length">
                                <div
                                    class="mb-1 text-[10px] font-medium uppercase tracking-wide text-muted-foreground"
                                >
                                    Related
                                </div>
                                <ul class="flex flex-col">
                                    <li
                                        v-for="child in issue.children"
                                        :key="child.id"
                                    >
                                        <Link
                                            :href="`/issues/${child.identifier}`"
                                            class="flex items-center gap-2 rounded px-1 py-1 text-[13px] text-foreground hover:bg-accent/60"
                                        >
                                            <StatusIcon
                                                :type="
                                                    child.state?.type ?? 'unstarted'
                                                "
                                                :color="child.state?.color"
                                            />
                                            <span
                                                class="font-mono text-[11px] text-muted-foreground"
                                                >{{ child.identifier }}</span
                                            >
                                            <span
                                                class="min-w-0 flex-1 truncate"
                                                >{{ child.title }}</span
                                            >
                                        </Link>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>
                </div>
            </aside>
        </div>
    </div>
</template>
