<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import StatusIcon from '@/components/repo/StatusIcon.vue';
import PriorityIcon from '@/components/repo/PriorityIcon.vue';
import Avatar from '@/components/repo/Avatar.vue';
import LabelBadge from '@/components/repo/LabelBadge.vue';
import ProjectChip from '@/components/repo/ProjectChip.vue';
import { renderMarkdown } from '@/lib/markdown';

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
        </header>

        <div class="flex min-h-0 flex-1">
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
                                    <PriorityIcon :priority="child.priority" :size="14" />
                                    <StatusIcon
                                        :type="child.state?.type ?? 'unstarted'"
                                        :color="child.state?.color"
                                    />
                                    <span class="font-mono text-[11px] text-muted-foreground">{{ child.identifier }}</span>
                                    <span class="min-w-0 flex-1 truncate text-[13px]">{{ child.title }}</span>
                                    <Avatar
                                        v-if="child.assignee"
                                        :name="child.assignee.name"
                                        :size="18"
                                    />
                                </Link>
                            </li>
                        </ul>
                    </section>

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
                                    <Avatar
                                        v-if="c.user"
                                        :name="c.user.name"
                                        :email="c.user.email"
                                        :size="20"
                                    />
                                    <span class="font-medium text-foreground">{{ c.user?.name ?? 'Unknown' }}</span>
                                    <span class="text-muted-foreground">{{ relativeTime(c.created_at) }}</span>
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
                <div class="space-y-4 text-[13px]">
                    <div>
                        <div class="mb-1 text-[11px] uppercase tracking-wide text-muted-foreground">Status</div>
                        <div class="flex items-center gap-2 text-foreground">
                            <StatusIcon
                                :type="issue.state?.type ?? 'unstarted'"
                                :color="issue.state?.color"
                            />
                            <span>{{ issue.state?.name ?? '—' }}</span>
                        </div>
                    </div>

                    <div>
                        <div class="mb-1 text-[11px] uppercase tracking-wide text-muted-foreground">Priority</div>
                        <div class="flex items-center gap-2 text-foreground">
                            <PriorityIcon :priority="issue.priority" :size="14" />
                            <span>{{ issue.priority_label }}</span>
                        </div>
                    </div>

                    <div>
                        <div class="mb-1 text-[11px] uppercase tracking-wide text-muted-foreground">Assignee</div>
                        <div v-if="issue.assignee" class="flex items-center gap-2 text-foreground">
                            <Avatar :name="issue.assignee.name" :email="issue.assignee.email" :size="20" />
                            <span>{{ issue.assignee.name }}</span>
                        </div>
                        <span v-else class="text-muted-foreground">Unassigned</span>
                    </div>

                    <div v-if="issue.creator">
                        <div class="mb-1 text-[11px] uppercase tracking-wide text-muted-foreground">Created by</div>
                        <div class="flex items-center gap-2 text-foreground">
                            <Avatar :name="issue.creator.name" :email="issue.creator.email" :size="20" />
                            <span>{{ issue.creator.name }}</span>
                        </div>
                    </div>

                    <div v-if="issue.project">
                        <div class="mb-1 text-[11px] uppercase tracking-wide text-muted-foreground">Project</div>
                        <ProjectChip
                            :name="issue.project.name"
                            :color="issue.project.color"
                            :slug="issue.project.slug"
                            :href="`/projects/${issue.project.slug}`"
                        />
                    </div>

                    <div v-if="issue.labels.length">
                        <div class="mb-1.5 text-[11px] uppercase tracking-wide text-muted-foreground">Labels</div>
                        <div class="flex flex-wrap gap-1.5">
                            <LabelBadge
                                v-for="label in issue.labels"
                                :key="label.id"
                                :name="label.name"
                                :color="label.color"
                            />
                        </div>
                    </div>

                    <div v-if="issue.estimate !== null">
                        <div class="mb-1 text-[11px] uppercase tracking-wide text-muted-foreground">Estimate</div>
                        <div class="text-foreground">{{ issue.estimate }} pt</div>
                    </div>

                    <div v-if="issue.due_date">
                        <div class="mb-1 text-[11px] uppercase tracking-wide text-muted-foreground">Due</div>
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
