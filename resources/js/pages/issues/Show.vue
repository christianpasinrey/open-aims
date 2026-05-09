<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronDown,
    ChevronRight,
    File as FileIcon,
    Link as LinkIcon,
    Loader2,
    PanelRightClose,
    PanelRightOpen,
    Paperclip,
    Plus,
    Star,
    Upload,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import Avatar from '@/components/repo/Avatar.vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useFavourites } from '@/composables/useFavourites';

import AssigneePicker from '@/components/repo/issues/AssigneePicker.vue';
import CyclePicker from '@/components/repo/issues/CyclePicker.vue';
import DueDatePicker from '@/components/repo/issues/DueDatePicker.vue';
import EstimatePicker from '@/components/repo/issues/EstimatePicker.vue';
import GithubLinksPanel from '@/components/repo/github/GithubLinksPanel.vue';
import InlineDescriptionEditor from '@/components/repo/issues/InlineDescriptionEditor.vue';
import InlineTitleEditor from '@/components/repo/issues/InlineTitleEditor.vue';
import IssueActions from '@/components/repo/issues/IssueActions.vue';
import IssueActivityRow from '@/components/repo/issues/IssueActivityRow.vue';
import LabelsPicker from '@/components/repo/issues/LabelsPicker.vue';
import LinkedPullRequests from '@/components/repo/issues/LinkedPullRequests.vue';
import MarkdownContent from '@/components/repo/MarkdownContent.vue';
import PriorityPicker from '@/components/repo/issues/PriorityPicker.vue';
import ProjectPicker from '@/components/repo/issues/ProjectPicker.vue';
import StatusPicker from '@/components/repo/issues/StatusPicker.vue';
import StatusIcon from '@/components/repo/StatusIcon.vue';

type State = {
    id: number;
    name: string;
    type: string;
    color: string;
    position: number;
};
type Label = { id: number; name: string; color?: string | null };
type User = { id: number; name: string; email: string };
type Cycle = {
    id: number;
    number: number;
    name: string | null;
    starts_at: string | null;
    ends_at: string | null;
};
type Project = {
    id: number;
    name: string;
    slug: string;
    color: string | null;
    icon: string | null;
};
type Issue = {
    id: number;
    identifier: string;
    number: number;
    title: string;
    description: string | null;
    git_branch_name: string | null;
    priority: number;
    priority_label: string;
    estimate: number | null;
    due_date: string | null;
    state: { id: number; name: string; type: string; color: string } | null;
    assignee: User | null;
    creator: User | null;
    project: Project | null;
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
    resources: Array<{
        id: number;
        type: 'file' | 'link';
        name: string;
        url: string | null;
        mime_type: string | null;
        size: number | null;
        created_at: string | null;
        creator: { id: number; name: string; email: string } | null;
    }>;
    completed_at?: string | null;
    canceled_at?: string | null;
    created_at: string | null;
    updated_at: string | null;
};
type LegacyLinkedPullRequest = {
    id: number;
    number: number;
    title: string;
    state: 'open' | 'closed' | 'merged' | string;
    url: string;
    branch_name: string;
    author_login: string | null;
    opened_at: string | null;
    closed_at: string | null;
    merged_at: string | null;
};
type LinkedBranch = {
    id: number;
    name: string;
    head_sha: string | null;
    repo_full_name: string;
    html_url: string | null;
    last_pushed_at: string | null;
    link_id: number;
    auto: boolean;
    linked_at: string | null;
};
type LinkedPullRequest = {
    id: number;
    number: number;
    title: string;
    state: string;
    merged: boolean;
    head_branch_name: string | null;
    html_url: string | null;
    link_id: number;
    auto: boolean;
    linked_at: string | null;
};
type AvailableGithubSource = {
    kind: 'branch' | 'pull_request';
    id: number;
    label: string;
    sublabel: string;
};
type Comment = {
    id: number;
    body: string;
    user: User | null;
    created_at: string | null;
    edited_at: string | null;
};

type ActivityActor = { id: number; name: string; email: string };
type Activity = {
    id: number;
    kind: string;
    payload: Record<string, unknown> | null;
    occurred_at: string | null;
    actor: ActivityActor | null;
};
type RelationStub = {
    identifier: string;
    title: string;
    state: { name: string; type: string; color: string } | null;
};
type Relations = {
    blocks: RelationStub[];
    blocked_by: RelationStub[];
    related: RelationStub[];
    duplicate_of: RelationStub[];
};

const props = defineProps<{
    team: { id: number; name: string; key: string; color: string | null };
    issue: Issue;
    comments: Comment[];
    states: State[];
    cycles: Cycle[];
    labels: Label[];
    projects: Project[];
    priorities: Record<string, string>;
    legacy_linked_pull_requests?: LegacyLinkedPullRequest[];
    linked_branches?: LinkedBranch[];
    linked_pull_requests?: LinkedPullRequest[];
    available_github_sources?: AvailableGithubSource[];
    activities?: Activity[];
    relations?: Relations;
}>();

type FeedItem =
    | { kind: 'comment'; id: number; at: string | null; comment: Comment }
    | { kind: 'activity'; id: number; at: string | null; activity: Activity };

const activityFeed = computed<FeedItem[]>(() => {
    const items: FeedItem[] = [];
    for (const c of props.comments ?? []) {
        items.push({ kind: 'comment', id: c.id, at: c.created_at, comment: c });
    }
    for (const a of props.activities ?? []) {
        items.push({
            kind: 'activity',
            id: a.id,
            at: a.occurred_at,
            activity: a,
        });
    }
    items.sort((x, y) => {
        const xt = x.at ? new Date(x.at).getTime() : 0;
        const yt = y.at ? new Date(y.at).getTime() : 0;
        return xt - yt;
    });
    return items;
});

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
function relativeTime(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const d = new Date(iso).getTime();
    const diff = Math.max(0, Date.now() - d);
    const m = Math.floor(diff / 60000);

    if (m < 60) {
        return `${m}m ago`;
    }

    const h = Math.floor(m / 60);

    if (h < 24) {
        return `${h}h ago`;
    }

    const days = Math.floor(h / 24);

    if (days < 30) {
        return `${days}d ago`;
    }

    return fmtDate(iso);
}

function dayMs(iso: string): number {
    const [y, m, d] = iso.slice(0, 10).split('-').map(Number);

    return new Date(y!, (m ?? 1) - 1, d ?? 1).getTime();
}

// ─── Favourites ─────────────────────────────────────────────────────────
const { isFavourited, toggle: toggleFavServer } = useFavourites();
const isStarred = computed<boolean>(() =>
    isFavourited('issue', props.issue.id),
);
function toggleStar() {
    toggleFavServer({
        kind: 'issue',
        href: `/issues/${props.issue.identifier}`,
        label: `${props.issue.identifier} ${props.issue.title}`,
        icon: 'Circle',
        color: props.team.color ?? null,
        target_type: 'App\\Modules\\Issues\\Models\\Issue',
        target_id: props.issue.id,
    });
}

// ─── Right rail collapse (per-section + whole rail) ─────────────────────
const RAIL_KEY = 'aims:issue-rail';
const railCollapsed = ref<boolean>(false);
const sectionCollapsed = ref<Record<string, boolean>>({
    properties: false,
    labels: false,
    project: false,
    resources: false,
    github: false,
    relations: false,
});

function toggleRail() {
    railCollapsed.value = !railCollapsed.value;
    persistRailState();
}
function toggleSection(
    key:
        | 'properties'
        | 'labels'
        | 'project'
        | 'resources'
        | 'github'
        | 'relations',
) {
    sectionCollapsed.value = {
        ...sectionCollapsed.value,
        [key]: !sectionCollapsed.value[key],
    };
    persistRailState();
}
function persistRailState() {
    try {
        window.localStorage.setItem(
            RAIL_KEY,
            JSON.stringify({
                collapsed: railCollapsed.value,
                sections: sectionCollapsed.value,
            }),
        );
    } catch {
        // ignore
    }
}

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }
    try {
        const raw = window.localStorage.getItem(RAIL_KEY);
        if (!raw) {
            return;
        }
        const parsed = JSON.parse(raw) as {
            collapsed?: boolean;
            sections?: Record<string, boolean>;
        };
        if (typeof parsed.collapsed === 'boolean') {
            railCollapsed.value = parsed.collapsed;
        }
        if (parsed.sections) {
            sectionCollapsed.value = {
                ...sectionCollapsed.value,
                ...parsed.sections,
            };
        }
    } catch {
        // ignore
    }
});

const isOverdue = computed<boolean>(() => {
    const iso = props.issue.due_date;

    if (!iso) {
        return false;
    }

    const target = dayMs(iso);
    const now = new Date();
    const today = new Date(
        now.getFullYear(),
        now.getMonth(),
        now.getDate(),
    ).getTime();
    const completed =
        props.issue.state?.type === 'completed' ||
        props.issue.state?.type === 'canceled';

    return target < today && !completed;
});

// =================================================================
// Resources: upload file or attach link
// =================================================================
const resourceFileInput = ref<HTMLInputElement | null>(null);
const resourceUploading = ref<boolean>(false);

function pickFile() {
    resourceFileInput.value?.click();
}
function onResourceFile(e: Event) {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) {
        return;
    }

    resourceUploading.value = true;
    router.post(
        `/issues/${props.issue.identifier}/resources`,
        {
            type: 'file',
            file,
            name: file.name,
        },
        {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                toast.success(`Uploaded "${file.name}"`);
            },
            onError: (errors) => {
                const first = Object.values(errors)[0];
                toast.error(
                    (first as string | undefined) ?? 'Could not upload file',
                );
            },
            onFinish: () => {
                resourceUploading.value = false;
                if (resourceFileInput.value) {
                    resourceFileInput.value.value = '';
                }
            },
        },
    );
}

const linkDialogOpen = ref<boolean>(false);
const linkForm = ref<{ name: string; url: string }>({ name: '', url: '' });
const linkSubmitting = ref<boolean>(false);
const linkError = ref<string | null>(null);

function openLinkDialog() {
    linkForm.value = { name: '', url: '' };
    linkError.value = null;
    linkDialogOpen.value = true;
}
function submitLink() {
    const name = linkForm.value.name.trim();
    const url = linkForm.value.url.trim();
    if (!name || !url) {
        linkError.value = 'Name and URL are required.';
        return;
    }

    linkSubmitting.value = true;
    router.post(
        `/issues/${props.issue.identifier}/resources`,
        { type: 'link', name, url },
        {
            preserveScroll: true,
            onSuccess: () => {
                linkDialogOpen.value = false;
                toast.success(`Saved "${name}"`);
            },
            onError: (errors) => {
                linkError.value =
                    (Object.values(errors)[0] as string | undefined) ??
                    'Could not save link.';
            },
            onFinish: () => {
                linkSubmitting.value = false;
            },
        },
    );
}

function deleteResource(id: number, name: string) {
    router.delete(`/issues/${props.issue.identifier}/resources/${id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(`Removed "${name}"`);
        },
    });
}

function fmtBytes(bytes: number | null): string {
    if (bytes === null || bytes === undefined) {
        return '';
    }
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    const kb = bytes / 1024;
    if (kb < 1024) {
        return `${kb.toFixed(1)} KB`;
    }
    return `${(kb / 1024).toFixed(1)} MB`;
}
</script>

<template>
    <Head :title="`${issue.identifier} — ${issue.title}`" />

    <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden">
        <header
            class="flex shrink-0 items-center gap-3 border-b border-border px-4 py-2.5"
        >
            <Link
                :href="
                    issue.project
                        ? `/projects/${issue.project.slug}?tab=issues`
                        : `/issues?team=${team.key}`
                "
                class="text-muted-foreground transition-colors hover:text-foreground"
                :aria-label="
                    issue.project
                        ? `Back to ${issue.project.name}`
                        : 'Back to issues'
                "
                :title="
                    issue.project
                        ? `Back to ${issue.project.name}`
                        : 'Back to issues'
                "
            >
                <ArrowLeft class="size-4" />
            </Link>
            <span
                class="flex size-5 items-center justify-center rounded-md text-[10px] font-semibold text-white"
                :style="{ backgroundColor: team.color || '#6366f1' }"
            >
                {{ team.key.charAt(0) }}
            </span>
            <span class="font-mono text-[12px] text-muted-foreground">{{
                issue.identifier
            }}</span>

            <button
                type="button"
                :class="[
                    'transition-colors',
                    isStarred
                        ? 'text-amber-400 hover:text-amber-500'
                        : 'text-muted-foreground hover:text-foreground',
                ]"
                :aria-label="isStarred ? 'Unfavourite' : 'Favourite'"
                :title="isStarred ? 'Unfavourite' : 'Favourite'"
                @click="toggleStar"
            >
                <Star
                    class="size-3.5"
                    :fill="isStarred ? 'currentColor' : 'none'"
                />
            </button>

            <button
                type="button"
                class="ml-auto rounded-md p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground"
                :aria-label="
                    railCollapsed ? 'Show right rail' : 'Hide right rail'
                "
                :title="railCollapsed ? 'Show right rail' : 'Hide right rail'"
                @click="toggleRail"
            >
                <component
                    :is="railCollapsed ? PanelRightOpen : PanelRightClose"
                    class="size-3.5"
                />
            </button>
            <IssueActions
                :identifier="issue.identifier"
                :title="issue.title"
                :related="
                    issue.children.map((c) => ({
                        identifier: c.identifier,
                        title: c.title,
                        state: c.state,
                    }))
                "
            />
        </header>

        <div class="flex min-h-0 flex-1">
            <div class="flex min-w-0 flex-1 flex-col overflow-y-auto">
                <div class="mx-auto w-full max-w-3xl px-8 py-8">
                    <InlineTitleEditor
                        :identifier="issue.identifier"
                        :title="issue.title"
                    />

                    <div class="mt-6">
                        <InlineDescriptionEditor
                            :identifier="issue.identifier"
                            :description="issue.description"
                        />
                    </div>

                    <section class="mt-10">
                        <h2
                            class="mb-3 text-[12px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Activity
                        </h2>
                        <div
                            v-if="!activityFeed.length"
                            class="text-[13px] text-muted-foreground"
                        >
                            No activity yet.
                        </div>
                        <ul v-else class="flex flex-col">
                            <template
                                v-for="item in activityFeed"
                                :key="`${item.kind}-${item.id}`"
                            >
                                <li
                                    v-if="item.kind === 'comment'"
                                    class="my-1 rounded-md border border-border bg-card p-3"
                                >
                                    <div
                                        class="flex items-center gap-2 text-[12px]"
                                    >
                                        <Avatar
                                            v-if="item.comment.user"
                                            :name="item.comment.user.name"
                                            :email="item.comment.user.email"
                                            :size="20"
                                        />
                                        <span
                                            class="font-medium text-foreground"
                                            >{{
                                                item.comment.user?.name ??
                                                'Unknown'
                                            }}</span
                                        >
                                        <span class="text-muted-foreground">{{
                                            relativeTime(
                                                item.comment.created_at,
                                            )
                                        }}</span>
                                    </div>
                                    <MarkdownContent
                                        :source="item.comment.body"
                                        class="mt-2"
                                    />
                                </li>
                                <IssueActivityRow
                                    v-else
                                    :activity="item.activity"
                                />
                            </template>
                        </ul>
                    </section>
                </div>
            </div>

            <aside
                v-if="!railCollapsed"
                class="hidden w-[300px] shrink-0 overflow-y-auto border-l border-border bg-background/40 px-3 py-3 lg:block"
            >
                <div class="space-y-2">
                    <!-- Properties -->
                    <section
                        class="rounded-lg border border-border/60 bg-card/40 px-3 py-2.5"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase hover:text-foreground"
                            :aria-expanded="!sectionCollapsed.properties"
                            @click="toggleSection('properties')"
                        >
                            <span>Properties</span>
                            <component
                                :is="
                                    sectionCollapsed.properties
                                        ? ChevronRight
                                        : ChevronDown
                                "
                                class="size-3 opacity-60"
                            />
                        </button>
                        <div
                            v-show="!sectionCollapsed.properties"
                            class="mt-1.5 flex flex-col gap-0.5"
                        >
                            <StatusPicker
                                :identifier="issue.identifier"
                                :states="states"
                                :current="issue.state"
                            />
                            <PriorityPicker
                                :identifier="issue.identifier"
                                :current="issue.priority"
                                :current-label="issue.priority_label"
                                :priorities="priorities"
                            />
                            <AssigneePicker
                                :identifier="issue.identifier"
                                :current="issue.assignee"
                            />
                            <CyclePicker
                                :identifier="issue.identifier"
                                :cycles="cycles"
                                :current="issue.cycle"
                            />
                            <DueDatePicker
                                :identifier="issue.identifier"
                                :current="issue.due_date"
                                :overdue="isOverdue"
                            />
                            <EstimatePicker
                                :identifier="issue.identifier"
                                :current="issue.estimate"
                            />
                        </div>
                    </section>

                    <!-- Labels -->
                    <section
                        class="rounded-lg border border-border/60 bg-card/40 px-3 py-2.5"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase hover:text-foreground"
                            :aria-expanded="!sectionCollapsed.labels"
                            @click="toggleSection('labels')"
                        >
                            <span>Labels</span>
                            <component
                                :is="
                                    sectionCollapsed.labels
                                        ? ChevronRight
                                        : ChevronDown
                                "
                                class="size-3 opacity-60"
                            />
                        </button>
                        <div v-show="!sectionCollapsed.labels" class="mt-1.5">
                            <LabelsPicker
                                :identifier="issue.identifier"
                                :labels="labels"
                                :current="issue.labels"
                            />
                        </div>
                    </section>

                    <!-- Project -->
                    <section
                        class="rounded-lg border border-border/60 bg-card/40 px-3 py-2.5"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase hover:text-foreground"
                            :aria-expanded="!sectionCollapsed.project"
                            @click="toggleSection('project')"
                        >
                            <span>Project</span>
                            <component
                                :is="
                                    sectionCollapsed.project
                                        ? ChevronRight
                                        : ChevronDown
                                "
                                class="size-3 opacity-60"
                            />
                        </button>
                        <div v-show="!sectionCollapsed.project" class="mt-1.5">
                            <ProjectPicker
                                :identifier="issue.identifier"
                                :projects="projects"
                                :current="issue.project"
                            />
                        </div>
                    </section>

                    <!-- Resources -->
                    <section
                        class="rounded-lg border border-border/60 bg-card/40 px-3 py-2.5"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase hover:text-foreground"
                            :aria-expanded="!sectionCollapsed.resources"
                            @click="toggleSection('resources')"
                        >
                            <span>Resources</span>
                            <component
                                :is="
                                    sectionCollapsed.resources
                                        ? ChevronRight
                                        : ChevronDown
                                "
                                class="size-3 opacity-60"
                            />
                        </button>
                        <div
                            v-show="!sectionCollapsed.resources"
                            class="mt-1.5"
                        >
                            <input
                                ref="resourceFileInput"
                                type="file"
                                class="hidden"
                                @change="onResourceFile"
                            />
                            <ul
                                v-if="issue.resources?.length"
                                class="mb-2 divide-y divide-border rounded-md border border-border"
                            >
                                <li
                                    v-for="r in issue.resources"
                                    :key="r.id"
                                    class="group grid grid-cols-[auto_1fr_auto_auto] items-center gap-2 px-2.5 py-1.5"
                                >
                                    <component
                                        :is="
                                            r.type === 'link'
                                                ? LinkIcon
                                                : r.mime_type?.startsWith(
                                                        'image/',
                                                    )
                                                  ? Paperclip
                                                  : FileIcon
                                        "
                                        class="size-3.5 text-muted-foreground"
                                    />
                                    <a
                                        v-if="r.url"
                                        :href="r.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="min-w-0 truncate text-[13px] text-foreground hover:underline"
                                        :title="r.name"
                                    >
                                        {{ r.name }}
                                    </a>
                                    <span
                                        v-else
                                        class="min-w-0 truncate text-[13px] text-muted-foreground"
                                    >
                                        {{ r.name }}
                                    </span>
                                    <span
                                        v-if="r.size"
                                        class="text-[10.5px] text-muted-foreground tabular-nums"
                                    >
                                        {{ fmtBytes(r.size) }}
                                    </span>
                                    <span v-else></span>
                                    <button
                                        type="button"
                                        class="rounded p-0.5 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 hover:bg-accent hover:text-rose-400"
                                        :aria-label="`Remove ${r.name}`"
                                        :title="`Remove ${r.name}`"
                                        @click="deleteResource(r.id, r.name)"
                                    >
                                        <X class="size-3.5" />
                                    </button>
                                </li>
                            </ul>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <button
                                        type="button"
                                        :disabled="resourceUploading"
                                        class="flex w-full items-center gap-2 rounded-md border border-dashed border-border px-3 py-2 text-[13px] text-muted-foreground transition-colors hover:bg-accent/40 hover:text-foreground disabled:opacity-50"
                                    >
                                        <Loader2
                                            v-if="resourceUploading"
                                            class="size-3.5 animate-spin"
                                        />
                                        <Plus v-else class="size-3.5" />
                                        {{
                                            resourceUploading
                                                ? 'Uploading…'
                                                : 'Add document or link…'
                                        }}
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="start"
                                    class="w-52"
                                >
                                    <DropdownMenuItem @select="pickFile">
                                        <Upload class="size-3.5" />
                                        Upload file
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @select="openLinkDialog">
                                        <LinkIcon class="size-3.5" />
                                        Add link
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </section>

                    <!-- Linked branches & PRs -->
                    <section
                        class="rounded-lg border border-border/60 bg-card/40 px-3 py-2.5"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase hover:text-foreground"
                            :aria-expanded="!sectionCollapsed.github"
                            @click="toggleSection('github')"
                        >
                            <span>Linked branches &amp; PRs</span>
                            <component
                                :is="
                                    sectionCollapsed.github
                                        ? ChevronRight
                                        : ChevronDown
                                "
                                class="size-3 opacity-60"
                            />
                        </button>
                        <div v-show="!sectionCollapsed.github" class="mt-1.5">
                            <GithubLinksPanel
                                linkable-type="issue"
                                :linkable-id="issue.id"
                                :branches="linked_branches ?? []"
                                :pull-requests="linked_pull_requests ?? []"
                                :available="available_github_sources ?? []"
                            />
                        </div>
                    </section>

                    <!-- Relations -->
                    <section
                        v-if="
                            issue.parent ||
                            issue.children.length ||
                            (legacy_linked_pull_requests &&
                                legacy_linked_pull_requests.length) ||
                            issue.git_branch_name
                        "
                        class="rounded-lg border border-border/60 bg-card/40 px-3 py-2.5"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase hover:text-foreground"
                            :aria-expanded="!sectionCollapsed.relations"
                            @click="toggleSection('relations')"
                        >
                            <span>Relations</span>
                            <component
                                :is="
                                    sectionCollapsed.relations
                                        ? ChevronRight
                                        : ChevronDown
                                "
                                class="size-3 opacity-60"
                            />
                        </button>

                        <div
                            v-show="!sectionCollapsed.relations"
                            class="mt-2 space-y-3"
                        >
                            <div v-if="issue.parent">
                                <div
                                    class="mb-1 text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
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
                                    class="mb-1 text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    Sub-issues
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
                                                    child.state?.type ??
                                                    'unstarted'
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

                            <LinkedPullRequests
                                :pull-requests="legacy_linked_pull_requests ?? []"
                                :branch-name="issue.git_branch_name"
                            />
                        </div>
                    </section>
                </div>
            </aside>
        </div>

        <!-- Add link dialog -->
        <Dialog v-model:open="linkDialogOpen">
            <DialogContent class="sm:max-w-[460px]">
                <DialogHeader>
                    <DialogTitle>Add link</DialogTitle>
                    <DialogDescription>
                        Save an external URL alongside this issue.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitLink">
                    <div class="space-y-1">
                        <label
                            class="text-[12px] font-medium text-foreground"
                            for="issue-link-name"
                            >Name</label
                        >
                        <Input
                            id="issue-link-name"
                            v-model="linkForm.name"
                            type="text"
                            placeholder="e.g. Design spec"
                            class="h-8 text-[13px]"
                            autofocus
                        />
                    </div>
                    <div class="space-y-1">
                        <label
                            class="text-[12px] font-medium text-foreground"
                            for="issue-link-url"
                            >URL</label
                        >
                        <Input
                            id="issue-link-url"
                            v-model="linkForm.url"
                            type="url"
                            placeholder="https://…"
                            class="h-8 text-[13px]"
                        />
                    </div>
                    <p v-if="linkError" class="text-[12px] text-rose-400">
                        {{ linkError }}
                    </p>
                    <DialogFooter>
                        <DialogClose
                            class="rounded-md px-3 py-1.5 text-[13px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        >
                            Cancel
                        </DialogClose>
                        <button
                            type="submit"
                            :disabled="linkSubmitting"
                            class="inline-flex items-center gap-1.5 rounded-md bg-foreground px-3 py-1.5 text-[13px] font-medium text-background hover:opacity-90 disabled:opacity-50"
                        >
                            <Loader2
                                v-if="linkSubmitting"
                                class="size-3.5 animate-spin"
                            />
                            {{ linkSubmitting ? 'Saving…' : 'Save link' }}
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
