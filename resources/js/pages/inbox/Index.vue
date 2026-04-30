<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Bell,
    Check,
    CheckCheck,
    Filter,
    Inbox,
    Link as LinkIcon,
    MessageSquare,
    MoreHorizontal,
    PenSquare,
    Star,
    UserPlus,
    History,
    Network,
    GitBranch,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Avatar from '@/components/repo/Avatar.vue';
import LabelBadge from '@/components/repo/LabelBadge.vue';
import PriorityIcon from '@/components/repo/PriorityIcon.vue';
import ProjectChip from '@/components/repo/ProjectChip.vue';
import StatusIcon from '@/components/repo/StatusIcon.vue';
import { useFavourites } from '@/composables/useFavourites';
import { renderMarkdown } from '@/lib/markdown';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type IssueState = { name: string; type: string; color: string };
type Team = { key: string; name: string; color: string | null };
type Actor = { id: number; name: string; email: string };
type FeedIssue = {
    id: number;
    identifier: string;
    title: string;
    priority: number;
    state: IssueState | null;
    team: Team | null;
};
type EntryKind = 'assigned' | 'created' | 'commented' | 'project_update';
type Entry = {
    kind: EntryKind;
    occurred_at: string;
    issue: FeedIssue;
    actor: Actor | null;
    snippet: string | null;
};

type PreviewLabel = { id: number; name: string; color: string | null };
type PreviewProject = {
    name: string;
    slug: string;
    color: string | null;
    icon: string | null;
};
type PreviewComment = {
    id: number;
    body: string;
    user: { name: string; email: string } | null;
    created_at: string | null;
};
type Preview = {
    identifier: string;
    title: string;
    description: string | null;
    priority: number;
    state: IssueState | null;
    assignee: { id: number; name: string; email: string } | null;
    creator: { id: number; name: string; email: string } | null;
    project: PreviewProject | null;
    labels: PreviewLabel[];
    comments: PreviewComment[];
    team: Team;
    updated_at: string | null;
    created_at: string | null;
};

const props = defineProps<{
    feed: Entry[];
    counts: { total: number; assigned: number; comments: number };
    preview: Preview | null;
}>();

function selectEntry(identifier: string) {
    router.get(
        '/inbox',
        { preview: identifier },
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

function clearPreview() {
    router.get('/inbox', {}, { preserveScroll: true, preserveState: true, replace: true });
}

function priorityLabel(p: number): string {
    return ['No priority', 'Urgent', 'High', 'Medium', 'Low'][p] ?? 'No priority';
}

const previewDescription = computed<string>(() =>
    renderMarkdown(props.preview?.description ?? null),
);
const previewComments = computed<Record<number, string>>(() =>
    Object.fromEntries(
        (props.preview?.comments ?? []).map((c) => [c.id, renderMarkdown(c.body)]),
    ),
);

const { isFavourited, toggle } = useFavourites();
const starred = computed(() => isFavourited('inbox', '/inbox'));
function toggleStar() {
    toggle({
        kind: 'inbox',
        href: '/inbox',
        label: 'Inbox',
        icon: 'Inbox',
    });
}
const filterAssigned = ref(true);
const filterCreated = ref(true);
const filterCommented = ref(true);

function relativeTime(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const d = new Date(iso).getTime();
    const diff = Math.max(0, Date.now() - d);
    const m = Math.floor(diff / 60000);

    if (m < 1) {
        return 'just now';
    }

    if (m < 60) {
        return `${m}m`;
    }

    const h = Math.floor(m / 60);

    if (h < 24) {
        return `${h}h`;
    }

    const days = Math.floor(h / 24);

    if (days < 30) {
        return `${days}d`;
    }

    const months = Math.floor(days / 30);

    if (months < 12) {
        return `${months}mo`;
    }

    return `${Math.floor(months / 12)}y`;
}

function actionLabel(e: Entry): string {
    switch (e.kind) {
        case 'assigned':
            return 'assigned this to you';
        case 'created':
            return 'updated an issue you created';
        case 'commented':
            return 'commented';
        case 'project_update':
            return 'posted a project update';
        default:
            return 'updated';
    }
}

function actionIcon(kind: Entry['kind']) {
    switch (kind) {
        case 'assigned':
            return UserPlus;
        case 'created':
            return PenSquare;
        case 'commented':
            return MessageSquare;
        default:
            return Inbox;
    }
}

const filteredFeed = computed<Entry[]>(() =>
    (props.feed ?? []).filter((e) => {
        if (e.kind === 'assigned' && !filterAssigned.value) {
            return false;
        }

        if (e.kind === 'created' && !filterCreated.value) {
            return false;
        }

        if (e.kind === 'commented' && !filterCommented.value) {
            return false;
        }

        return true;
    }),
);

const grouped = computed(() => {
    const today: Entry[] = [];
    const yesterday: Entry[] = [];
    const earlier: Entry[] = [];
    const now = Date.now();
    const dayMs = 24 * 60 * 60 * 1000;

    for (const e of filteredFeed.value) {
        const ts = new Date(e.occurred_at).getTime();
        const ageDays = (now - ts) / dayMs;

        if (ageDays < 1) {
            today.push(e);
        } else if (ageDays < 2) {
            yesterday.push(e);
        } else {
            earlier.push(e);
        }
    }

    return [
        { label: 'Today', entries: today },
        { label: 'Yesterday', entries: yesterday },
        { label: 'Earlier', entries: earlier },
    ].filter((g) => g.entries.length > 0);
});
</script>

<template>
    <Head title="Inbox" />

    <div class="flex h-full flex-1 overflow-hidden">
    <!-- LEFT: feed list -->
    <aside class="flex w-[400px] shrink-0 flex-col border-r border-border">
        <header
            class="flex shrink-0 items-center gap-2 border-b border-border px-4 py-2.5"
        >
            <Inbox class="size-4 text-muted-foreground" />
            <h1 class="text-[13px] font-medium">Inbox</h1>
            <span class="text-[12px] text-muted-foreground">{{
                filteredFeed.length
            }}</span>

            <button
                type="button"
                class="ml-1 rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                :title="starred ? 'Unfavourite' : 'Favourite'"
                @click="toggleStar"
            >
                <Star
                    class="size-3.5"
                    :class="{ 'fill-yellow-400 text-yellow-400': starred }"
                />
            </button>

            <div class="ml-auto flex items-center gap-1">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="flex items-center gap-1 rounded-md px-2 py-1 text-[12.5px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            title="Filter"
                        >
                            <Filter class="size-3.5" />
                            Filter
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-52">
                        <DropdownMenuLabel
                            class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            By type
                        </DropdownMenuLabel>
                        <DropdownMenuCheckboxItem
                            :checked="filterAssigned"
                            @update:checked="filterAssigned = $event"
                            @select="(e: Event) => e.preventDefault()"
                        >
                            Assigned to me
                        </DropdownMenuCheckboxItem>
                        <DropdownMenuCheckboxItem
                            :checked="filterCreated"
                            @update:checked="filterCreated = $event"
                            @select="(e: Event) => e.preventDefault()"
                        >
                            Created by me
                        </DropdownMenuCheckboxItem>
                        <DropdownMenuCheckboxItem
                            :checked="filterCommented"
                            @update:checked="filterCommented = $event"
                            @select="(e: Event) => e.preventDefault()"
                        >
                            Commented
                        </DropdownMenuCheckboxItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuLabel
                            class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            By status
                        </DropdownMenuLabel>
                        <DropdownMenuCheckboxItem disabled>
                            Read / Unread (soon)
                        </DropdownMenuCheckboxItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <button
                    type="button"
                    class="flex items-center gap-1 rounded-md px-2 py-1 text-[12.5px] text-muted-foreground/70"
                    disabled
                    title="Mark all as read (soon)"
                >
                    <CheckCheck class="size-3.5" />
                    Mark all as read
                </button>
            </div>
        </header>

        <div
            v-if="!filteredFeed.length"
            class="flex flex-1 items-center justify-center px-6 py-16 text-center"
        >
            <div class="max-w-sm">
                <div
                    class="mx-auto flex size-10 items-center justify-center rounded-full bg-muted"
                >
                    <Inbox class="size-5 text-muted-foreground" />
                </div>
                <h2 class="mt-4 text-base font-medium">
                    You&rsquo;re all caught up
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Notifications about issues you&rsquo;re assigned to, created
                    or commented on will appear here.
                </p>
            </div>
        </div>

        <div v-else class="flex-1 overflow-y-auto">
            <section v-for="group in grouped" :key="group.label">
                <div
                    class="sticky top-0 z-10 bg-muted/40 px-4 py-1.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase backdrop-blur"
                >
                    {{ group.label }}
                </div>
                <ul class="divide-y divide-border">
                    <li
                        v-for="(entry, idx) in group.entries"
                        :key="`${entry.kind}-${entry.issue.id}-${idx}`"
                        class="group/row relative"
                    >
                        <button
                            type="button"
                            :class="[
                                'flex w-full items-start gap-3 px-4 py-2.5 text-left transition-colors',
                                preview?.identifier === entry.issue.identifier
                                    ? 'bg-accent/60'
                                    : 'hover:bg-accent/40',
                            ]"
                            @click="selectEntry(entry.issue.identifier)"
                        >
                            <Avatar
                                v-if="entry.actor"
                                :name="entry.actor.name"
                                :email="entry.actor.email"
                                :size="24"
                            />
                            <span
                                v-else
                                class="flex size-6 items-center justify-center rounded-full bg-muted text-muted-foreground"
                            >
                                <component
                                    :is="actionIcon(entry.kind)"
                                    class="size-3"
                                />
                            </span>

                            <div class="min-w-0 flex-1">
                                <div
                                    class="flex items-center gap-2 text-[13px]"
                                >
                                    <span class="font-medium text-foreground">{{
                                        entry.actor?.name ?? 'System'
                                    }}</span>
                                    <span class="text-muted-foreground">{{
                                        actionLabel(entry)
                                    }}</span>
                                    <span class="text-muted-foreground">·</span>
                                    <span class="text-muted-foreground">{{
                                        relativeTime(entry.occurred_at)
                                    }}</span>
                                </div>

                                <div
                                    class="mt-1 flex min-w-0 items-center gap-2 text-[12.5px]"
                                >
                                    <span
                                        v-if="entry.issue.team"
                                        class="flex size-4 shrink-0 items-center justify-center rounded text-[9px] font-semibold text-white"
                                        :style="{
                                            backgroundColor:
                                                entry.issue.team.color ||
                                                '#6366f1',
                                        }"
                                    >
                                        {{ entry.issue.team.key.charAt(0) }}
                                    </span>
                                    <PriorityIcon
                                        :priority="entry.issue.priority"
                                    />
                                    <StatusIcon
                                        :type="
                                            entry.issue.state?.type ??
                                            'unstarted'
                                        "
                                        :color="entry.issue.state?.color"
                                    />
                                    <span
                                        class="font-mono text-[11px] text-muted-foreground"
                                        >{{ entry.issue.identifier }}</span
                                    >
                                    <span
                                        class="min-w-0 truncate text-foreground"
                                        >{{ entry.issue.title }}</span
                                    >
                                </div>

                                <p
                                    v-if="entry.snippet"
                                    class="mt-1.5 line-clamp-2 rounded-md border border-border bg-card px-2.5 py-1.5 text-[12.5px] text-muted-foreground"
                                >
                                    {{ entry.snippet }}
                                </p>
                            </div>
                        </button>
                        <button
                            type="button"
                            class="absolute top-3 right-3 hidden items-center gap-1 rounded-md border border-border bg-background px-1.5 py-0.5 text-[11px] text-muted-foreground group-hover/row:flex hover:bg-accent disabled:opacity-50"
                            disabled
                            title="Mark as read (soon)"
                        >
                            <Check class="size-3" />
                            Mark read
                        </button>
                    </li>
                </ul>
            </section>
        </div>
    </aside>

    <!-- MIDDLE: preview body or empty state -->
    <div v-if="!preview" class="flex min-w-0 flex-1 items-center justify-center text-muted-foreground">
        <div class="text-center">
            <div class="mx-auto flex size-10 items-center justify-center rounded-full bg-muted">
                <Inbox class="size-5" />
            </div>
            <p class="mt-3 text-[13px]">Select a notification to preview</p>
        </div>
    </div>
    <section v-else class="flex min-w-0 flex-1 flex-col">
        <!-- Breadcrumb / actions -->
        <header
            class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4 py-2.5"
        >
            <nav class="flex min-w-0 items-center gap-2 text-[12.5px] text-muted-foreground">
                <Link
                    v-if="preview.project"
                    :href="`/projects/${preview.project.slug}`"
                    class="flex shrink-0 items-center gap-1.5 truncate transition-colors hover:text-foreground"
                >
                    <ProjectChip
                        :name="preview.project.name"
                        :color="preview.project.color"
                        :icon="preview.project.icon"
                    />
                </Link>
                <span v-if="preview.project" class="text-muted-foreground/60">›</span>
                <Link
                    :href="`/issues/${preview.identifier}`"
                    class="truncate text-foreground transition-colors hover:underline"
                >
                    <span class="font-mono text-[11.5px]">{{ preview.identifier }}</span>
                    <span class="ml-1.5">{{ preview.title }}</span>
                </Link>
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
                <button
                    type="button"
                    class="rounded-md p-1.5 hover:bg-accent hover:text-foreground"
                    aria-label="History"
                >
                    <History class="size-3.5" />
                </button>
                <button
                    type="button"
                    class="rounded-md p-1.5 hover:bg-accent hover:text-foreground"
                    aria-label="Close preview"
                    @click="clearPreview"
                >
                    <MoreHorizontal class="size-3.5" />
                </button>
            </div>
        </header>

        <!-- Body -->
        <div class="flex-1 overflow-y-auto">
            <div class="mx-auto w-full max-w-3xl px-8 py-8">
                <h1 class="text-[22px] font-semibold leading-tight tracking-tight text-foreground">
                    {{ preview.title }}
                </h1>
                <div
                    v-if="previewDescription"
                    class="markdown-body mt-6"
                    v-html="previewDescription"
                ></div>
                <p
                    v-else
                    class="mt-6 text-[14px] italic text-muted-foreground"
                >
                    No description.
                </p>

                <section v-if="preview.comments.length" class="mt-10">
                    <h2 class="mb-3 text-[12px] font-medium uppercase tracking-wide text-muted-foreground">
                        Activity
                    </h2>
                    <ul class="space-y-4">
                        <li
                            v-for="c in preview.comments"
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
                                v-html="previewComments[c.id]"
                            ></div>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </section>

    <!-- RIGHT: properties rail (mirror of issue/Show) -->
    <aside
        v-if="preview"
        class="hidden w-[280px] shrink-0 overflow-y-auto border-l border-border bg-muted/20 px-5 py-5 lg:block"
    >
        <div class="mb-3 flex items-center gap-1 text-muted-foreground">
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
                aria-label="Branch"
            >
                <GitBranch class="size-3.5" />
            </button>
            <button
                type="button"
                class="rounded-md p-1.5 hover:bg-accent hover:text-foreground"
                aria-label="Network"
            >
                <Network class="size-3.5" />
            </button>
        </div>

        <div class="space-y-5 text-[13px]">
            <!-- Properties -->
            <div>
                <div class="mb-2 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                    Properties
                </div>
                <div class="space-y-1.5">
                    <div class="flex items-center gap-2 text-foreground">
                        <StatusIcon
                            :type="preview.state?.type ?? 'unstarted'"
                            :color="preview.state?.color"
                        />
                        <span>{{ preview.state?.name ?? '—' }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-foreground">
                        <PriorityIcon :priority="preview.priority" />
                        <span>{{ priorityLabel(preview.priority) }}</span>
                    </div>
                    <div v-if="preview.assignee" class="flex items-center gap-2 text-foreground">
                        <Avatar
                            :name="preview.assignee.name"
                            :email="preview.assignee.email"
                            :size="18"
                        />
                        <span>{{ preview.assignee.name }}</span>
                    </div>
                    <div v-else class="flex items-center gap-2 text-muted-foreground">
                        <span class="size-3.5 rounded-full border border-dashed border-border"></span>
                        <span>Unassigned</span>
                    </div>
                </div>
            </div>

            <!-- Labels -->
            <div>
                <div class="mb-2 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                    Labels
                </div>
                <div v-if="preview.labels.length" class="flex flex-wrap gap-1.5">
                    <LabelBadge
                        v-for="l in preview.labels"
                        :key="l.id"
                        :name="l.name"
                        :color="l.color"
                    />
                </div>
                <span v-else class="text-muted-foreground">—</span>
            </div>

            <!-- Project -->
            <div>
                <div class="mb-2 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                    Project
                </div>
                <ProjectChip
                    v-if="preview.project"
                    :name="preview.project.name"
                    :color="preview.project.color"
                    :icon="preview.project.icon"
                    :slug="preview.project.slug"
                    :href="`/projects/${preview.project.slug}`"
                />
                <span v-else class="text-muted-foreground">—</span>
            </div>

            <div class="border-t border-border pt-4 text-[12px] text-muted-foreground">
                Updated {{ relativeTime(preview.updated_at) }}<br />
                Created {{ relativeTime(preview.created_at) }}
            </div>
        </div>
    </aside>
    </div>
</template>
