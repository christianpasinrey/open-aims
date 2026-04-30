<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Inbox,
    UserPlus,
    PenSquare,
    MessageSquare,
    Star,
    Filter,
    CheckCheck,
    Check,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Avatar from '@/components/repo/Avatar.vue';
import PriorityIcon from '@/components/repo/PriorityIcon.vue';
import StatusIcon from '@/components/repo/StatusIcon.vue';
import { useFavourites } from '@/composables/useFavourites';
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

const props = defineProps<{
    feed: Entry[];
    counts: { total: number; assigned: number; comments: number };
}>();

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

    <div class="flex h-full flex-1 flex-col overflow-hidden">
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
                        <Link
                            :href="`/issues/${entry.issue.identifier}`"
                            class="flex items-start gap-3 px-4 py-2.5 hover:bg-accent/40"
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
                        </Link>
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
    </div>
</template>
