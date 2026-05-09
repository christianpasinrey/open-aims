<script setup lang="ts">
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Bell,
    Calendar,
    ChevronDown,
    ChevronRight,
    Diamond,
    Flag,
    Link as LinkIcon,
    Loader2,
    MoreHorizontal,
    Package,
    PanelRightClose,
    PanelRightOpen,
    Plus,
    Star,
    Trash2,
    UserPlus,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import Avatar from '@/components/repo/Avatar.vue';
import LabelBadge from '@/components/repo/LabelBadge.vue';
import MarkdownContent from '@/components/repo/MarkdownContent.vue';
import PriorityIcon from '@/components/repo/PriorityIcon.vue';
import ProjectIcon from '@/components/repo/ProjectIcon.vue';
import RichEditor from '@/components/repo/RichEditor.vue';
import StatusIcon from '@/components/repo/StatusIcon.vue';
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
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useFavourites } from '@/composables/useFavourites';
import { startedProgressByState } from '@/lib/states';

type Project = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    state: string | null;
    priority: number;
    color: string | null;
    icon: string | null;
    start_date: string | null;
    target_date: string | null;
    completed_at: string | null;
    lead: { id: number; name: string; email: string } | null;
    members: Array<{
        id: number;
        name: string;
        email: string;
        role: string | null;
    }>;
    milestones: Array<{
        id: number;
        name: string;
        description: string | null;
        target_date: string | null;
        issue_count: number;
        percent: number;
    }>;
    teams: Array<{
        id: number;
        name: string;
        key: string;
        color: string | null;
    }>;
};
type IssueState = { name: string; type: string; color: string };
type Label = { id: number; name: string; color?: string | null };
type Issue = {
    id: number;
    identifier: string;
    title: string;
    priority: number;
    state_name: string | null;
    state: IssueState | null;
    assignee: { id: number; name: string } | null;
    labels: Label[];
    updated_at: string | null;
};
type State = {
    id: number;
    name: string;
    type: string;
    color: string;
    position: number;
};
type AssigneeStat = {
    user: { id: number; name: string; email: string } | null;
    total: number;
    completed: number;
    percent: number;
};
type WorkspaceMember = { id: number; name: string; email: string };

const props = defineProps<{
    project: Project;
    issues: Issue[];
    states: State[];
    progress: {
        total: number;
        completed: number;
        started: number;
        percent: number;
    };
    assignees: AssigneeStat[];
    labels: Array<{ id: number; name: string; color?: string | null }>;
    available_labels: Array<{
        id: number;
        name: string;
        color?: string | null;
    }>;
    available_members: WorkspaceMember[];
    tab: 'overview' | 'activity' | 'issues';
}>();

const PROJECT_STATES = [
    'backlog',
    'planned',
    'started',
    'paused',
    'completed',
    'canceled',
] as const;
const STATE_LABELS: Record<string, string> = {
    backlog: 'Backlog',
    planned: 'Planned',
    started: 'In progress',
    paused: 'Paused',
    completed: 'Completed',
    canceled: 'Canceled',
};

const TYPE_RANK: Record<string, number> = {
    triage: 0,
    started: 1,
    unstarted: 2,
    backlog: 3,
    completed: 4,
    canceled: 5,
};
const stateOrder = computed(() =>
    [...props.states].sort((a, b) => {
        const ta = TYPE_RANK[a.type] ?? 99;
        const tb = TYPE_RANK[b.type] ?? 99;

        if (ta !== tb) {
            return ta - tb;
        }

        return a.position - b.position;
    }),
);
const grouped = computed(() => {
    const buckets = new Map<string, Issue[]>();

    for (const i of props.issues) {
        const key = i.state_name ?? '—';

        if (!buckets.has(key)) {
            buckets.set(key, []);
        }

        buckets.get(key)!.push(i);
    }

    const ordered: Array<{
        state:
            | State
            | {
                  id: number | null;
                  name: string;
                  type: string;
                  color: string;
                  position: number;
              };
        issues: Issue[];
    }> = [];

    for (const s of stateOrder.value) {
        const bucket = buckets.get(s.name);

        if (bucket && bucket.length) {
            ordered.push({ state: s, issues: bucket });
            buckets.delete(s.name);
        }
    }

    for (const [name, list] of buckets.entries()) {
        ordered.push({
            state: {
                id: null,
                name,
                type: 'unstarted',
                color: '#94a3b8',
                position: 999,
            },
            issues: list,
        });
    }

    return ordered;
});

const startedProgress = computed(() => startedProgressByState(props.states));

const teamForBreadcrumb = computed(() => props.project.teams[0] ?? null);

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

function tabHref(tab: 'overview' | 'activity' | 'issues') {
    return tab === 'overview'
        ? `/projects/${props.project.slug}`
        : `/projects/${props.project.slug}?tab=${tab}`;
}

const ringR = 5;
const ringC = 2 * Math.PI * ringR;
const ringDashOffset = computed(
    () => ringC * (1 - props.progress.percent / 100),
);
const ringStroke = computed(() => {
    if (props.project.state === 'canceled') {
        return '#a1a1aa';
    }

    if (props.progress.percent >= 100 || props.project.state === 'completed') {
        return '#10b981';
    }

    if (props.progress.percent > 0) {
        return '#f59e0b';
    }

    return '#a1a1aa';
});

const projectStatusType = computed<
    'backlog' | 'started' | 'unstarted' | 'completed' | 'canceled'
>(() => {
    switch (props.project.state) {
        case 'started':
            return 'started';
        case 'paused':
            return 'unstarted';
        case 'completed':
            return 'completed';
        case 'canceled':
            return 'canceled';
        case 'planned':
        case 'backlog':
        default:
            return 'backlog';
    }
});
function statusTypeFor(
    state: string,
): 'backlog' | 'started' | 'unstarted' | 'completed' | 'canceled' {
    switch (state) {
        case 'started':
            return 'started';
        case 'paused':
            return 'unstarted';
        case 'completed':
            return 'completed';
        case 'canceled':
            return 'canceled';
        default:
            return 'backlog';
    }
}

function projectStateLabel() {
    return STATE_LABELS[props.project.state ?? 'backlog'] ?? 'Backlog';
}

// ---- Right-rail Progress > tabs ----
const progressTab = ref<'assignees' | 'labels' | 'cycles'>('assignees');

const burndownIdealPoints = '12,18 244,122';
const burndownActualPoints = computed<string>(() => {
    const startX = 12;
    const endX = 244;
    const topY = 18;
    const bottomY = 122;
    const total = props.progress.total;
    const completed = props.progress.completed;

    if (total <= 0) {
        return `${startX},${topY} ${endX},${topY}`;
    }

    const t = Math.max(0, Math.min(1, completed / total));
    const segs = 6;
    const pts: string[] = [];

    for (let i = 0; i <= segs; i++) {
        const x = startX + (i / segs) * (endX - startX);
        const eased = Math.pow(i / segs, 1.4) * t;
        const y = topY + eased * (bottomY - topY);
        pts.push(`${x.toFixed(1)},${y.toFixed(1)}`);
    }

    return pts.join(' ');
});

const donutR = 5;
const donutC = 2 * Math.PI * donutR;
function donutOffset(percent: number): number {
    return donutC * (1 - Math.max(0, Math.min(100, percent)) / 100);
}
function donutStroke(percent: number): string {
    if (percent >= 100) {
        return '#10b981';
    }

    if (percent > 0) {
        return '#6366f1';
    }

    return '#a1a1aa';
}

// =================================================================
// Mutations: PATCH /projects/{slug}
// =================================================================
function patchProject(payload: Record<string, FormDataConvertible>) {
    router.patch(`/projects/${props.project.slug}`, payload, {
        preserveScroll: true,
        preserveState: true,
    });
}

function setState(state: string) {
    if (state === props.project.state) {
        return;
    }

    patchProject({ state });
}
function setLead(userId: number | null) {
    if ((props.project.lead?.id ?? null) === userId) {
        return;
    }

    patchProject({ lead_user_id: userId });
}
const PRIORITY_LABELS: Record<number, string> = {
    0: 'No priority',
    1: 'Urgent',
    2: 'High',
    3: 'Medium',
    4: 'Low',
};
const PRIORITY_ORDER = [1, 2, 3, 4, 0];
function setPriority(p: number) {
    if ((props.project.priority ?? 0) === p) {
        return;
    }

    patchProject({ priority: p });
}

// =================================================================
// Members: attach / detach
// =================================================================
function attachMember(userId: number) {
    router.post(
        `/projects/${props.project.slug}/members`,
        { user_id: userId },
        { preserveScroll: true },
    );
}
function detachMember(userId: number) {
    router.delete(`/projects/${props.project.slug}/members/${userId}`, {
        preserveScroll: true,
    });
}

// =================================================================
// Labels: attach / detach + quick-create
// =================================================================
function attachLabel(labelId: number) {
    router.post(
        `/projects/${props.project.slug}/labels`,
        { label_id: labelId },
        { preserveScroll: true },
    );
}
function detachLabel(labelId: number) {
    router.delete(`/projects/${props.project.slug}/labels/${labelId}`, {
        preserveScroll: true,
    });
}

const labelQuery = ref<string>('');
const labelCreating = ref<boolean>(false);
const labelMenuOpen = ref<boolean>(false);

const filteredAvailableLabels = computed(() => {
    const q = labelQuery.value.trim().toLowerCase();
    if (!q) {
        return props.available_labels;
    }
    return props.available_labels.filter((l) =>
        l.name.toLowerCase().includes(q),
    );
});
const exactLabelMatch = computed(() => {
    const q = labelQuery.value.trim().toLowerCase();
    if (!q) {
        return false;
    }
    return [...props.available_labels, ...props.labels].some(
        (l) => l.name.toLowerCase() === q,
    );
});

async function quickCreateLabel() {
    const name = labelQuery.value.trim();
    const teamKey = props.project.teams[0]?.key;
    if (!name || !teamKey || labelCreating.value) {
        return;
    }

    labelCreating.value = true;
    try {
        const res = await fetch(`/teams/${teamKey}/labels`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    (
                        document.querySelector(
                            'meta[name="csrf-token"]',
                        ) as HTMLMetaElement | null
                    )?.content ?? '',
            },
            body: JSON.stringify({ name }),
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            toast.error(
                (err?.message as string | undefined) ??
                    'Could not create label',
            );
            return;
        }
        const created = (await res.json()) as { id: number; name: string };
        labelQuery.value = '';
        // Attach right away — server reload via Inertia will refresh the list.
        router.post(
            `/projects/${props.project.slug}/labels`,
            { label_id: created.id },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Created and attached "${created.name}"`);
                },
            },
        );
    } catch {
        toast.error('Could not create label');
    } finally {
        labelCreating.value = false;
    }
}
function setStartDate(date: string) {
    patchProject({ start_date: date === '' ? null : date });
}
function setTargetDate(date: string) {
    patchProject({ target_date: date === '' ? null : date });
}

// ---- Workspace members fetched on demand for the lead picker ----
const workspaceMembers = ref<WorkspaceMember[]>([]);
const membersLoaded = ref<boolean>(false);
async function loadMembers() {
    if (membersLoaded.value) {
        return;
    }

    try {
        const res = await fetch('/workspace/members', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (res.ok) {
            const json = (await res.json()) as { data: WorkspaceMember[] };
            workspaceMembers.value = json.data;
        }
    } catch {
        // silent — picker just shows the current lead
    } finally {
        membersLoaded.value = true;
    }
}

// =================================================================
// Inline editing: name + description
// =================================================================
const editingName = ref<boolean>(false);
const nameDraft = ref<string>('');
const nameInput = ref<HTMLInputElement | null>(null);

function startEditName() {
    nameDraft.value = props.project.name;
    editingName.value = true;
    nextTick(() => {
        nameInput.value?.focus();
        nameInput.value?.select();
    });
}
function commitName() {
    const v = nameDraft.value.trim();
    editingName.value = false;

    if (v === '' || v === props.project.name) {
        return;
    }

    patchProject({ name: v });
}
function cancelName() {
    editingName.value = false;
}

const editingDesc = ref<boolean>(false);
const descDraft = ref<string>('');
const descSaving = ref<boolean>(false);
const descEditorRef = ref<InstanceType<typeof RichEditor> | null>(null);

function startEditDesc() {
    descDraft.value = props.project.description ?? '';
    editingDesc.value = true;
    nextTick(() => {
        descEditorRef.value?.focus();
    });
}
function commitDesc() {
    const next = descDraft.value.trim();
    const original = (props.project.description ?? '').trim();

    if (next === original) {
        editingDesc.value = false;

        return;
    }

    descSaving.value = true;
    router.patch(
        `/projects/${props.project.slug}`,
        { description: next === '' ? null : descDraft.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                editingDesc.value = false;
            },
            onFinish: () => {
                descSaving.value = false;
            },
        },
    );
}
function cancelDesc() {
    descDraft.value = props.project.description ?? '';
    editingDesc.value = false;
}

// =================================================================
// Favourites (server-side)
// =================================================================
const { isFavourited, toggle: toggleFav } = useFavourites();
const projectHref = computed<string>(() => `/projects/${props.project.slug}`);
const isFavourite = computed<boolean>(() =>
    isFavourited('project', projectHref.value),
);
function toggleFavourite() {
    toggleFav({
        kind: 'project',
        href: projectHref.value,
        label: props.project.name,
        icon: props.project.icon ?? 'FolderKanban',
        color: props.project.color ?? null,
    });
}

function copyLink() {
    if (typeof navigator === 'undefined' || !navigator.clipboard) {
        return;
    }

    void navigator.clipboard.writeText(window.location.href);
}

// =================================================================
// Issues tab: collapse persistence + inline composer
// =================================================================
const COLLAPSE_KEY = computed(
    () => `aims:project-issue-collapse:${props.project.slug}`,
);
const collapsed = ref<Set<string>>(new Set());
function toggleGroup(name: string) {
    const n = new Set(collapsed.value);

    if (n.has(name)) {
        n.delete(name);
    } else {
        n.add(name);
    }

    collapsed.value = n;

    try {
        window.localStorage.setItem(
            COLLAPSE_KEY.value,
            JSON.stringify(Array.from(n)),
        );
    } catch {
        // ignore
    }
}

const composerStateName = ref<string | null>(null);
const composerTitle = ref<string>('');
const composerInput = ref<HTMLInputElement | null>(null);
const composerSubmitting = ref<boolean>(false);

function openComposer(stateName: string) {
    composerStateName.value = stateName;
    composerTitle.value = '';
    nextTick(() => composerInput.value?.focus());
}
function cancelComposer() {
    composerStateName.value = null;
    composerTitle.value = '';
}
function submitComposer() {
    const title = composerTitle.value.trim();
    const teamKey = props.project.teams[0]?.key;

    if (!title || !teamKey) {
        cancelComposer();

        return;
    }

    const stateId = (props.states ?? []).find(
        (s) => s.name === composerStateName.value,
    )?.id;
    composerSubmitting.value = true;
    router.post(
        '/issues',
        {
            title,
            team_key: teamKey,
            project_id: props.project.id,
            ...(stateId !== undefined ? { state_id: stateId } : {}),
        },
        {
            preserveScroll: true,
            onFinish: () => {
                composerSubmitting.value = false;
                cancelComposer();
            },
        },
    );
}

// =================================================================
// Milestone dialog
// =================================================================
const milestoneDialogOpen = ref<boolean>(false);
const milestoneForm = ref<{
    name: string;
    description: string;
    target_date: string;
}>({
    name: '',
    description: '',
    target_date: '',
});
const milestoneSubmitting = ref<boolean>(false);
const milestoneError = ref<string | null>(null);

function openMilestoneDialog() {
    milestoneForm.value = { name: '', description: '', target_date: '' };
    milestoneError.value = null;
    milestoneDialogOpen.value = true;
}
function submitMilestone() {
    if (!milestoneForm.value.name.trim()) {
        milestoneError.value = 'Name is required.';

        return;
    }

    milestoneSubmitting.value = true;
    milestoneError.value = null;
    const payload: Record<string, FormDataConvertible> = {
        name: milestoneForm.value.name.trim(),
    };

    if (milestoneForm.value.description.trim() !== '') {
        payload.description = milestoneForm.value.description.trim();
    }

    if (milestoneForm.value.target_date !== '') {
        payload.target_date = milestoneForm.value.target_date;
    }

    router.post(`/projects/${props.project.slug}/milestones`, payload, {
        preserveScroll: true,
        onSuccess: () => {
            milestoneDialogOpen.value = false;
            toast.success('Milestone created');
        },
        onError: (errors) => {
            const first = Object.values(errors)[0];
            milestoneError.value =
                (first as string | undefined) ?? 'Could not create milestone.';
        },
        onFinish: () => {
            milestoneSubmitting.value = false;
        },
    });
}

// =================================================================
// Delete project (soft-delete with cascade to issues + milestones)
// =================================================================
const deleteDialogOpen = ref<boolean>(false);
const deleteSubmitting = ref<boolean>(false);

function openDeleteDialog() {
    deleteDialogOpen.value = true;
}

function confirmDelete() {
    deleteSubmitting.value = true;
    router.delete(`/projects/${props.project.slug}`, {
        preserveScroll: false,
        onSuccess: () => {
            deleteDialogOpen.value = false;
            toast.success(`Moved "${props.project.name}" to Trash`);
        },
        onError: () => {
            toast.error('Could not delete project');
        },
        onFinish: () => {
            deleteSubmitting.value = false;
        },
    });
}

// =================================================================
// Right rail collapse (per-section + whole rail)
// =================================================================
const railCollapsed = ref<boolean>(false);
const sectionCollapsed = ref<Record<string, boolean>>({
    properties: false,
    milestones: false,
    progress: false,
});

const RAIL_KEY = 'aims:project-rail';
function toggleRail() {
    railCollapsed.value = !railCollapsed.value;
    persistRailState();
}
function toggleSection(key: 'properties' | 'milestones' | 'progress') {
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

// =================================================================
// Mounted: load collapse state
// =================================================================
onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        const raw = window.localStorage.getItem(COLLAPSE_KEY.value);

        if (raw) {
            const arr = JSON.parse(raw) as string[];
            collapsed.value = new Set(Array.isArray(arr) ? arr : []);
        }

        const railRaw = window.localStorage.getItem(RAIL_KEY);
        if (railRaw) {
            const parsed = JSON.parse(railRaw) as {
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
        }

        loadMembers();
    } catch {
        // ignore
    }
});

watch(
    () => props.project.slug,
    () => {
        try {
            const raw = window.localStorage.getItem(COLLAPSE_KEY.value);
            collapsed.value = new Set(raw ? JSON.parse(raw) : []);
        } catch {
            // ignore
        }
    },
);
</script>

<template>
    <Head :title="project.name" />

    <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden">
        <!-- Top bar with breadcrumb -->
        <header
            class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4 py-2.5"
        >
            <nav class="flex min-w-0 items-center gap-2 text-[12.5px]">
                <Link
                    v-if="teamForBreadcrumb"
                    :href="`/projects?team=${teamForBreadcrumb.key}`"
                    class="flex shrink-0 items-center gap-1.5 text-muted-foreground transition-colors hover:text-foreground"
                >
                    <span
                        class="flex size-4 items-center justify-center rounded-md text-[9px] font-semibold text-white"
                        :style="{
                            backgroundColor:
                                teamForBreadcrumb.color || '#6366f1',
                        }"
                    >
                        {{ teamForBreadcrumb.key.charAt(0) }}
                    </span>
                    <span>{{ teamForBreadcrumb.name }}</span>
                </Link>
                <span class="text-muted-foreground">›</span>
                <ProjectIcon
                    :icon="project.icon"
                    :color="project.color"
                    :size="14"
                    rounded="sm"
                />
                <h1 class="truncate text-foreground">{{ project.name }}</h1>
                <button
                    type="button"
                    :class="[
                        'transition-colors',
                        isFavourite
                            ? 'text-amber-400'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                    :aria-label="isFavourite ? 'Unfavourite' : 'Favourite'"
                    :title="isFavourite ? 'Unfavourite' : 'Favourite'"
                    @click="toggleFavourite"
                >
                    <Star
                        class="size-3.5"
                        :fill="isFavourite ? 'currentColor' : 'none'"
                    />
                </button>
            </nav>
            <div class="flex items-center gap-1 text-muted-foreground">
                <button
                    type="button"
                    class="rounded-md p-1.5 hover:bg-accent hover:text-foreground"
                    aria-label="Copy link"
                    title="Copy link"
                    @click="copyLink"
                >
                    <LinkIcon class="size-3.5" />
                </button>
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
                    :aria-label="
                        railCollapsed ? 'Show right rail' : 'Hide right rail'
                    "
                    :title="
                        railCollapsed ? 'Show right rail' : 'Hide right rail'
                    "
                    @click="toggleRail"
                >
                    <component
                        :is="railCollapsed ? PanelRightOpen : PanelRightClose"
                        class="size-3.5"
                    />
                </button>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="rounded-md p-1.5 hover:bg-accent hover:text-foreground"
                            aria-label="More"
                        >
                            <MoreHorizontal class="size-3.5" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-48">
                        <DropdownMenuItem disabled>Duplicate</DropdownMenuItem>
                        <DropdownMenuItem disabled>Archive</DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            class="text-rose-400 focus:text-rose-400"
                            @select="openDeleteDialog"
                        >
                            <Trash2 class="size-3.5" />
                            Delete project
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>

        <!-- Tabs -->
        <div
            class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4"
        >
            <nav class="flex items-center gap-1 py-2 text-[12.5px]">
                <Link
                    :href="tabHref('overview')"
                    :class="[
                        'rounded-md px-2 py-1 transition-colors',
                        tab === 'overview'
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                    ]"
                    >Overview</Link
                >
                <Link
                    :href="tabHref('activity')"
                    :class="[
                        'rounded-md px-2 py-1 transition-colors',
                        tab === 'activity'
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                    ]"
                    >Activity</Link
                >
                <Link
                    :href="tabHref('issues')"
                    :class="[
                        'rounded-md px-2 py-1 transition-colors',
                        tab === 'issues'
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                    ]"
                    >Issues</Link
                >
            </nav>
        </div>

        <!-- Body: split with right rail -->
        <div class="flex min-h-0 flex-1">
            <div class="flex min-w-0 flex-1 flex-col overflow-y-auto">
                <!-- OVERVIEW -->
                <div
                    v-if="tab === 'overview'"
                    class="mx-auto w-full max-w-3xl px-8 py-8"
                >
                    <ProjectIcon
                        :icon="project.icon"
                        :color="project.color"
                        :size="40"
                        rounded="lg"
                        class="mb-4"
                    />
                    <h2
                        v-if="!editingName"
                        class="-mx-1 cursor-text rounded-md px-1 py-0.5 text-[22px] font-semibold tracking-tight transition-colors hover:bg-accent/40"
                        @click="startEditName"
                    >
                        {{ project.name }}
                    </h2>
                    <input
                        v-else
                        ref="nameInput"
                        v-model="nameDraft"
                        type="text"
                        class="-mx-1 w-full rounded-md border border-input bg-transparent px-1 py-0.5 text-[22px] font-semibold tracking-tight outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                        @blur="commitName"
                        @keydown.enter.prevent="commitName"
                        @keydown.escape="cancelName"
                    />
                    <p
                        v-if="project.description"
                        class="mt-2 text-[14px] text-muted-foreground"
                    >
                        {{
                            project.description
                                .split('\n')
                                .find((l) => l.trim().length > 0) ?? ''
                        }}
                    </p>

                    <!-- Properties row -->
                    <div
                        class="mt-6 flex flex-wrap items-center gap-2 text-[12.5px]"
                    >
                        <span class="text-muted-foreground">Properties</span>

                        <!-- Status chip -->
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-2 py-0.5 text-foreground transition-colors hover:bg-accent/40"
                                >
                                    <StatusIcon :type="projectStatusType" />
                                    <span class="capitalize">{{
                                        projectStateLabel()
                                    }}</span>
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent class="w-44">
                                <DropdownMenuLabel
                                    >Set status</DropdownMenuLabel
                                >
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    v-for="key in PROJECT_STATES"
                                    :key="key"
                                    @select="setState(key)"
                                >
                                    <span class="flex items-center gap-2">
                                        <StatusIcon
                                            :type="statusTypeFor(key)"
                                            :size="12"
                                        />
                                        {{ STATE_LABELS[key] }}
                                    </span>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <!-- Lead chip -->
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button
                                    type="button"
                                    :class="[
                                        'inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-2 py-0.5 transition-colors hover:bg-accent/40',
                                        project.lead
                                            ? 'text-foreground'
                                            : 'text-muted-foreground',
                                    ]"
                                    @click="loadMembers"
                                >
                                    <Avatar
                                        v-if="project.lead"
                                        :name="project.lead.name"
                                        :email="project.lead.email"
                                        :size="14"
                                    />
                                    <span
                                        v-else
                                        class="size-3.5 rounded-full border border-dashed border-border"
                                    ></span>
                                    <span>{{
                                        project.lead?.name ?? 'No lead'
                                    }}</span>
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                class="max-h-72 w-56 overflow-y-auto"
                            >
                                <DropdownMenuLabel>Set lead</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem @select="setLead(null)">
                                    <span
                                        class="flex items-center gap-2 text-muted-foreground"
                                    >
                                        <span
                                            class="size-3.5 rounded-full border border-dashed border-border"
                                        ></span>
                                        No lead
                                    </span>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-for="m in workspaceMembers"
                                    :key="m.id"
                                    @select="setLead(m.id)"
                                >
                                    <span
                                        class="flex min-w-0 items-center gap-2"
                                    >
                                        <Avatar
                                            :name="m.name"
                                            :email="m.email"
                                            :size="14"
                                        />
                                        <span class="truncate">{{
                                            m.name
                                        }}</span>
                                    </span>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="
                                        !workspaceMembers.length &&
                                        membersLoaded
                                    "
                                    disabled
                                >
                                    No workspace members
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <!-- Dates chip (popover-like dropdown with two date inputs) -->
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button
                                    type="button"
                                    :class="[
                                        'inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-2 py-0.5 transition-colors hover:bg-accent/40',
                                        project.target_date ||
                                        project.start_date
                                            ? 'text-foreground'
                                            : 'text-muted-foreground',
                                    ]"
                                >
                                    <Calendar class="size-3" />
                                    <span v-if="project.target_date">{{
                                        fmtDate(project.target_date)
                                    }}</span>
                                    <span v-else>Set dates</span>
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                class="w-64 p-3"
                                :side-offset="4"
                            >
                                <div class="space-y-2">
                                    <div class="space-y-1">
                                        <label
                                            class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                            >Start</label
                                        >
                                        <input
                                            type="date"
                                            :value="project.start_date ?? ''"
                                            class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-[13px]"
                                            @change="
                                                (e) =>
                                                    setStartDate(
                                                        (
                                                            e.target as HTMLInputElement
                                                        ).value,
                                                    )
                                            "
                                        />
                                    </div>
                                    <div class="space-y-1">
                                        <label
                                            class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                            >Target</label
                                        >
                                        <input
                                            type="date"
                                            :value="project.target_date ?? ''"
                                            class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-[13px]"
                                            @change="
                                                (e) =>
                                                    setTargetDate(
                                                        (
                                                            e.target as HTMLInputElement
                                                        ).value,
                                                    )
                                            "
                                        />
                                    </div>
                                </div>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <!-- Teams (read-only chips) -->
                        <span
                            v-for="t in project.teams"
                            :key="t.id"
                            class="inline-flex items-center gap-1 rounded-md border border-border bg-card px-2 py-0.5 text-[12px]"
                            :title="`${t.name} (read-only — TODO: edit teams endpoint)`"
                        >
                            <Package
                                class="size-3"
                                :style="{ color: t.color || '#6366f1' }"
                            />
                            <span class="text-foreground">{{ t.key }}</span>
                        </span>
                    </div>

                    <!-- Resources stub -->
                    <div class="mt-6">
                        <div
                            class="mb-2 text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Resources
                        </div>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded-md border border-dashed border-border px-3 py-2 text-[13px] text-muted-foreground transition-colors hover:bg-accent/40 hover:text-foreground"
                        >
                            <Plus class="size-3.5" /> Add document or link…
                        </button>
                    </div>

                    <!-- Update CTA -->
                    <div
                        class="mt-6 flex items-center justify-center rounded-md border border-border bg-muted/30 px-4 py-6 text-[13px] text-muted-foreground"
                    >
                        Write first project update
                    </div>

                    <!-- Description -->
                    <section class="mt-8">
                        <div
                            class="mb-3 flex items-center gap-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Description <ChevronDown class="size-3" />
                        </div>
                        <template v-if="editingDesc">
                            <RichEditor
                                ref="descEditorRef"
                                v-model="descDraft"
                                placeholder="Describe the project…"
                                autofocus
                                @blur="commitDesc"
                                @submit="commitDesc"
                                @cancel="cancelDesc"
                            />
                            <div
                                class="pointer-events-none mt-2 flex items-center gap-3 text-[11px] text-muted-foreground/80"
                            >
                                <span>
                                    <kbd class="font-mono">Ctrl</kbd>+<kbd
                                        class="font-mono"
                                        >Enter</kbd
                                    >
                                    to save
                                </span>
                                <span>
                                    <kbd class="font-mono">Esc</kbd> to cancel
                                </span>
                                <span
                                    v-if="descSaving"
                                    class="ml-auto inline-flex items-center gap-1"
                                >
                                    <span
                                        class="size-1.5 animate-pulse rounded-full bg-muted-foreground"
                                    ></span>
                                    Saving…
                                </span>
                            </div>
                        </template>
                        <MarkdownContent
                            v-else-if="project.description"
                            :source="project.description"
                            class="cursor-text rounded-md transition-colors hover:bg-accent/30"
                            @click="startEditDesc"
                        />
                        <button
                            v-else
                            type="button"
                            class="-mx-3 w-[calc(100%+1.5rem)] rounded-md px-3 py-2 text-left text-[14px] text-muted-foreground italic transition-colors hover:bg-accent/40"
                            @click="startEditDesc"
                        >
                            Add a description…
                        </button>
                    </section>

                    <!-- Milestones -->
                    <section class="mt-10">
                        <div class="mb-3 flex items-center justify-between">
                            <h3
                                class="text-[12px] font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Milestones
                            </h3>
                            <button
                                type="button"
                                class="rounded p-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                aria-label="New milestone"
                                title="New milestone"
                                @click="openMilestoneDialog"
                            >
                                <Plus class="size-3.5" />
                            </button>
                        </div>
                        <ul
                            v-if="project.milestones.length"
                            class="divide-y divide-border rounded-md border border-border"
                        >
                            <li
                                v-for="ms in project.milestones"
                                :key="ms.id"
                                class="px-3 py-2"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-2"
                                    >
                                        <Diamond
                                            class="size-3 shrink-0"
                                            :style="{
                                                color:
                                                    project.color || '#6366f1',
                                                fill:
                                                    project.color || '#6366f1',
                                            }"
                                        />
                                        <span
                                            class="truncate text-[13px] font-medium"
                                            >{{ ms.name }}</span
                                        >
                                    </div>
                                    <span
                                        v-if="ms.target_date"
                                        class="text-[12px] text-muted-foreground"
                                        >{{ fmtShort(ms.target_date) }}</span
                                    >
                                </div>
                                <p
                                    v-if="ms.description"
                                    class="mt-1 text-[12.5px] text-muted-foreground"
                                >
                                    {{ ms.description }}
                                </p>
                            </li>
                        </ul>
                        <p v-else class="text-[12.5px] text-muted-foreground">
                            Break the project into milestones to track progress
                            in stages.
                        </p>
                    </section>
                </div>

                <!-- ACTIVITY (placeholder) -->
                <div
                    v-else-if="tab === 'activity'"
                    class="flex flex-1 items-center justify-center px-6 py-12 text-center"
                >
                    <div class="max-w-sm">
                        <h2 class="text-base font-medium text-foreground">
                            No activity yet
                        </h2>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Project updates and changes will appear here.
                        </p>
                    </div>
                </div>

                <!-- ISSUES -->
                <div v-else class="flex-1 overflow-y-auto">
                    <div
                        v-if="!issues.length"
                        class="flex h-full items-center justify-center px-6 py-12 text-center"
                    >
                        <p class="text-sm text-muted-foreground">
                            No issues in this project.
                        </p>
                    </div>
                    <section v-for="group in grouped" :key="group.state.name">
                        <button
                            type="button"
                            class="sticky top-0 z-10 flex w-full items-center gap-2 bg-muted/40 px-4 py-1.5 text-left backdrop-blur transition-colors hover:bg-muted/60"
                            @click="toggleGroup(group.state.name)"
                        >
                            <component
                                :is="
                                    collapsed.has(group.state.name)
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
                            <span class="text-[12px] text-muted-foreground">{{
                                group.issues.length
                            }}</span>
                            <span
                                class="ml-auto rounded p-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                aria-label="New issue"
                                title="New issue"
                                @click.stop="openComposer(group.state.name)"
                            >
                                <Plus class="size-3.5" />
                            </span>
                        </button>
                        <!-- Inline composer -->
                        <div
                            v-if="composerStateName === group.state.name"
                            class="border-b border-border bg-background"
                        >
                            <form
                                class="flex items-center gap-2 px-4 py-1.5"
                                @submit.prevent="submitComposer"
                            >
                                <PriorityIcon :priority="0" />
                                <StatusIcon
                                    :type="group.state.type"
                                    :color="group.state.color"
                                    :progress="
                                        group.state.id != null
                                            ? startedProgress[group.state.id]
                                            : undefined
                                    "
                                />
                                <input
                                    ref="composerInput"
                                    v-model="composerTitle"
                                    type="text"
                                    placeholder="Issue title"
                                    class="min-w-0 flex-1 bg-transparent text-[13px] outline-none placeholder:text-muted-foreground"
                                    :disabled="composerSubmitting"
                                    @keydown.escape="cancelComposer"
                                />
                                <button
                                    type="button"
                                    class="rounded-md px-2 py-0.5 text-[11.5px] text-muted-foreground hover:bg-accent hover:text-foreground"
                                    @click="cancelComposer"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    class="rounded-md bg-foreground px-2 py-0.5 text-[11.5px] font-medium text-background hover:opacity-90 disabled:opacity-50"
                                    :disabled="
                                        !composerTitle.trim() ||
                                        composerSubmitting
                                    "
                                >
                                    Create
                                </button>
                            </form>
                        </div>
                        <ul
                            v-show="!collapsed.has(group.state.name)"
                            class="divide-y divide-border"
                        >
                            <li v-for="issue in group.issues" :key="issue.id">
                                <Link
                                    :href="`/issues/${issue.identifier}`"
                                    class="grid grid-cols-[auto_auto_64px_1fr_auto_42px_24px] items-center gap-2 px-4 py-1.5 hover:bg-accent/40"
                                >
                                    <PriorityIcon :priority="issue.priority" />
                                    <span
                                        class="font-mono text-[11px] text-muted-foreground tabular-nums"
                                        >{{ issue.identifier }}</span
                                    >
                                    <StatusIcon
                                        :type="issue.state?.type ?? 'unstarted'"
                                        :color="issue.state?.color"
                                    />
                                    <span
                                        class="min-w-0 truncate text-[13px]"
                                        >{{ issue.title }}</span
                                    >
                                    <div
                                        class="hidden items-center gap-1 lg:flex"
                                    >
                                        <LabelBadge
                                            v-for="label in issue.labels.slice(
                                                0,
                                                2,
                                            )"
                                            :key="label.id"
                                            :name="label.name"
                                            :color="label.color"
                                        />
                                    </div>
                                    <span
                                        class="text-right text-[11px] text-muted-foreground tabular-nums"
                                    >
                                        {{ fmtShort(issue.updated_at) }}
                                    </span>
                                    <Avatar
                                        v-if="issue.assignee"
                                        :name="issue.assignee.name"
                                        :size="20"
                                    />
                                    <span
                                        v-else
                                        class="size-5 rounded-full border border-dashed border-border"
                                    ></span>
                                </Link>
                            </li>
                        </ul>
                    </section>
                </div>
            </div>

            <!-- Right rail -->
            <aside
                v-if="!railCollapsed"
                class="hidden w-[300px] shrink-0 overflow-y-auto border-l border-border bg-background/40 px-3 py-3 lg:block"
            >
                <div class="space-y-2 text-[13px]">
                    <!-- ============== PROPERTIES ============== -->
                    <section
                        class="rounded-lg border border-border/60 bg-card/40 px-3 py-2.5"
                    >
                        <header class="mb-2 flex items-center justify-between">
                            <button
                                type="button"
                                class="flex items-center gap-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase hover:text-foreground"
                                :aria-expanded="!sectionCollapsed.properties"
                                @click="toggleSection('properties')"
                            >
                                Properties
                                <component
                                    :is="
                                        sectionCollapsed.properties
                                            ? ChevronRight
                                            : ChevronDown
                                    "
                                    class="size-3"
                                />
                            </button>
                            <button
                                type="button"
                                class="rounded p-0.5 text-muted-foreground hover:bg-accent hover:text-foreground"
                                aria-label="Add property"
                            >
                                <Plus class="size-3" />
                            </button>
                        </header>
                        <dl
                            v-show="!sectionCollapsed.properties"
                            class="grid grid-cols-[80px_1fr] items-center gap-x-3 gap-y-2"
                        >
                            <!-- Status -->
                            <dt class="text-[12.5px] text-muted-foreground">
                                Status
                            </dt>
                            <dd>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <button
                                            type="button"
                                            class="-mx-1 flex w-full items-center gap-1.5 rounded-md px-1 py-0.5 text-left text-[13px] text-foreground transition-colors hover:bg-accent/40"
                                        >
                                            <StatusIcon
                                                :type="projectStatusType"
                                            />
                                            <span class="capitalize">{{
                                                projectStateLabel()
                                            }}</span>
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent class="w-44">
                                        <DropdownMenuItem
                                            v-for="key in PROJECT_STATES"
                                            :key="key"
                                            @select="setState(key)"
                                        >
                                            <span
                                                class="flex items-center gap-2"
                                            >
                                                <StatusIcon
                                                    :type="statusTypeFor(key)"
                                                    :size="12"
                                                />
                                                {{ STATE_LABELS[key] }}
                                            </span>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </dd>

                            <!-- Priority -->
                            <dt class="text-[12.5px] text-muted-foreground">
                                Priority
                            </dt>
                            <dd>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <button
                                            type="button"
                                            :class="[
                                                '-mx-1 flex w-full items-center gap-1.5 rounded-md px-1 py-0.5 text-left text-[13px] transition-colors hover:bg-accent/40',
                                                project.priority === 0
                                                    ? 'text-muted-foreground'
                                                    : 'text-foreground',
                                            ]"
                                        >
                                            <PriorityIcon
                                                :priority="project.priority"
                                            />
                                            <span>{{
                                                PRIORITY_LABELS[
                                                    project.priority
                                                ] ?? 'No priority'
                                            }}</span>
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent class="w-44">
                                        <DropdownMenuItem
                                            v-for="p in PRIORITY_ORDER"
                                            :key="p"
                                            @select="setPriority(p)"
                                        >
                                            <span
                                                class="flex items-center gap-2"
                                            >
                                                <PriorityIcon
                                                    :priority="p"
                                                    :size="14"
                                                />
                                                {{ PRIORITY_LABELS[p] }}
                                            </span>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </dd>

                            <!-- Lead -->
                            <dt class="text-[12.5px] text-muted-foreground">
                                Lead
                            </dt>
                            <dd>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <button
                                            type="button"
                                            class="-mx-1 flex w-full items-center gap-1.5 rounded-md px-1 py-0.5 text-left text-[13px] transition-colors hover:bg-accent/40"
                                            @click="loadMembers"
                                        >
                                            <Avatar
                                                v-if="project.lead"
                                                :name="project.lead.name"
                                                :email="project.lead.email"
                                                :size="16"
                                            />
                                            <span
                                                v-else
                                                class="size-4 rounded-full border border-dashed border-border"
                                            ></span>
                                            <span
                                                :class="
                                                    project.lead
                                                        ? 'truncate text-foreground'
                                                        : 'text-muted-foreground'
                                                "
                                            >
                                                {{
                                                    project.lead?.name ??
                                                    'No lead'
                                                }}
                                            </span>
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        class="max-h-72 w-56 overflow-y-auto"
                                    >
                                        <DropdownMenuItem
                                            @select="setLead(null)"
                                        >
                                            <span
                                                class="flex items-center gap-2 text-muted-foreground"
                                            >
                                                <span
                                                    class="size-3.5 rounded-full border border-dashed border-border"
                                                ></span>
                                                No lead
                                            </span>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-for="m in workspaceMembers"
                                            :key="m.id"
                                            @select="setLead(m.id)"
                                        >
                                            <span
                                                class="flex min-w-0 items-center gap-2"
                                            >
                                                <Avatar
                                                    :name="m.name"
                                                    :email="m.email"
                                                    :size="14"
                                                />
                                                <span class="truncate">{{
                                                    m.name
                                                }}</span>
                                            </span>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </dd>

                            <!-- Members -->
                            <dt class="text-[12.5px] text-muted-foreground">
                                Members
                            </dt>
                            <dd>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <button
                                            type="button"
                                            class="-mx-1 flex w-full items-center gap-1.5 rounded-md px-1 py-0.5 text-left text-[12.5px] transition-colors hover:bg-accent/40"
                                        >
                                            <template
                                                v-if="project.members.length"
                                            >
                                                <span
                                                    class="flex items-center -space-x-1"
                                                >
                                                    <span
                                                        v-for="m in project.members.slice(
                                                            0,
                                                            5,
                                                        )"
                                                        :key="m.id"
                                                        class="ring-2 ring-[hsl(var(--background))]"
                                                    >
                                                        <Avatar
                                                            :name="m.name"
                                                            :email="m.email"
                                                            :size="18"
                                                        />
                                                    </span>
                                                </span>
                                                <span
                                                    v-if="
                                                        project.members.length >
                                                        5
                                                    "
                                                    class="text-[11px] text-muted-foreground tabular-nums"
                                                    >+{{
                                                        project.members.length -
                                                        5
                                                    }}</span
                                                >
                                            </template>
                                            <template v-else>
                                                <UserPlus
                                                    class="size-3.5 text-muted-foreground"
                                                />
                                                <span
                                                    class="text-muted-foreground"
                                                    >Add members</span
                                                >
                                            </template>
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        class="max-h-72 w-60 overflow-y-auto"
                                    >
                                        <DropdownMenuLabel
                                            >Project members</DropdownMenuLabel
                                        >
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            v-for="m in project.members"
                                            :key="`pm-${m.id}`"
                                            @select="detachMember(m.id)"
                                        >
                                            <span
                                                class="flex min-w-0 flex-1 items-center gap-2"
                                            >
                                                <Avatar
                                                    :name="m.name"
                                                    :email="m.email"
                                                    :size="14"
                                                />
                                                <span class="truncate">{{
                                                    m.name
                                                }}</span>
                                            </span>
                                            <span
                                                class="ml-2 text-[11px] text-muted-foreground"
                                                >Remove</span
                                            >
                                        </DropdownMenuItem>
                                        <template
                                            v-if="
                                                available_members &&
                                                available_members.length
                                            "
                                        >
                                            <DropdownMenuSeparator />
                                            <DropdownMenuLabel
                                                >Add member</DropdownMenuLabel
                                            >
                                            <DropdownMenuItem
                                                v-for="u in available_members"
                                                :key="`am-${u.id}`"
                                                @select="attachMember(u.id)"
                                            >
                                                <span
                                                    class="flex min-w-0 items-center gap-2"
                                                >
                                                    <Avatar
                                                        :name="u.name"
                                                        :email="u.email"
                                                        :size="14"
                                                    />
                                                    <span class="truncate">{{
                                                        u.name
                                                    }}</span>
                                                </span>
                                            </DropdownMenuItem>
                                        </template>
                                        <DropdownMenuItem
                                            v-else-if="!project.members.length"
                                            disabled
                                        >
                                            No workspace members
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </dd>

                            <!-- Dates: 📅 start → 🚩 target -->
                            <dt class="text-[12.5px] text-muted-foreground">
                                Dates
                            </dt>
                            <dd>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <button
                                            type="button"
                                            class="-mx-1 flex w-full flex-wrap items-center gap-1.5 rounded-md px-1 py-0.5 text-left text-[12.5px] transition-colors hover:bg-accent/40"
                                        >
                                            <span
                                                v-if="project.start_date"
                                                class="inline-flex items-center gap-1 text-foreground"
                                            >
                                                <Calendar
                                                    class="size-3 text-muted-foreground"
                                                />
                                                {{
                                                    fmtShort(project.start_date)
                                                }}
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center gap-1 rounded-md border border-dashed border-border px-1.5 py-0.5 text-muted-foreground"
                                            >
                                                <Calendar class="size-3" />
                                                Start
                                            </span>
                                            <span class="text-muted-foreground"
                                                >→</span
                                            >
                                            <span
                                                v-if="project.target_date"
                                                class="inline-flex items-center gap-1 text-foreground"
                                            >
                                                <Flag
                                                    class="size-3 text-muted-foreground"
                                                />
                                                {{
                                                    fmtShort(
                                                        project.target_date,
                                                    )
                                                }}
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center gap-1 rounded-md border border-dashed border-border px-1.5 py-0.5 text-muted-foreground"
                                            >
                                                <Flag class="size-3" />
                                                Target
                                            </span>
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent class="w-64 p-3">
                                        <div class="space-y-2">
                                            <div class="space-y-1">
                                                <label
                                                    class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                                    >Start</label
                                                >
                                                <input
                                                    type="date"
                                                    :value="
                                                        project.start_date ?? ''
                                                    "
                                                    class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-[13px]"
                                                    @change="
                                                        (e) =>
                                                            setStartDate(
                                                                (
                                                                    e.target as HTMLInputElement
                                                                ).value,
                                                            )
                                                    "
                                                />
                                            </div>
                                            <div class="space-y-1">
                                                <label
                                                    class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                                    >Target</label
                                                >
                                                <input
                                                    type="date"
                                                    :value="
                                                        project.target_date ??
                                                        ''
                                                    "
                                                    class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-[13px]"
                                                    @change="
                                                        (e) =>
                                                            setTargetDate(
                                                                (
                                                                    e.target as HTMLInputElement
                                                                ).value,
                                                            )
                                                    "
                                                />
                                            </div>
                                        </div>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </dd>

                            <!-- Teams (read-only) -->
                            <dt class="text-[12.5px] text-muted-foreground">
                                Teams
                            </dt>
                            <dd
                                class="flex flex-wrap gap-1"
                                title="TODO: edit teams endpoint not implemented yet"
                            >
                                <span
                                    v-for="t in project.teams"
                                    :key="t.id"
                                    class="inline-flex items-center gap-1 rounded-md border border-border bg-card px-1.5 py-px text-[11px] leading-[16px]"
                                >
                                    <Package
                                        class="size-3"
                                        :style="{ color: t.color || '#6366f1' }"
                                    />
                                    <span class="text-foreground">{{
                                        t.key
                                    }}</span>
                                </span>
                            </dd>

                            <!-- Labels -->
                            <dt class="text-[12.5px] text-muted-foreground">
                                Labels
                            </dt>
                            <dd>
                                <DropdownMenu v-model:open="labelMenuOpen">
                                    <DropdownMenuTrigger as-child>
                                        <button
                                            type="button"
                                            class="-mx-1 flex w-full flex-wrap items-center gap-1 rounded-md px-1 py-0.5 text-left text-[12.5px] transition-colors hover:bg-accent/40"
                                        >
                                            <template v-if="labels.length">
                                                <LabelBadge
                                                    v-for="l in labels"
                                                    :key="l.id"
                                                    :name="l.name"
                                                    :color="l.color"
                                                />
                                            </template>
                                            <template v-else>
                                                <Plus
                                                    class="size-3.5 text-muted-foreground"
                                                />
                                                <span
                                                    class="text-muted-foreground"
                                                    >Add label</span
                                                >
                                            </template>
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent class="w-64 p-0">
                                        <div class="border-b border-border p-2">
                                            <Input
                                                v-model="labelQuery"
                                                placeholder="Search or create…"
                                                class="h-7 text-[12.5px]"
                                                @keydown.enter.prevent="
                                                    quickCreateLabel
                                                "
                                            />
                                        </div>
                                        <div
                                            class="max-h-72 overflow-y-auto py-1"
                                        >
                                            <button
                                                v-for="l in labels"
                                                :key="`pl-${l.id}`"
                                                type="button"
                                                class="flex w-full items-center justify-between gap-2 px-2 py-1 text-left text-[12.5px] hover:bg-accent"
                                                @click="detachLabel(l.id)"
                                            >
                                                <LabelBadge
                                                    :name="l.name"
                                                    :color="l.color"
                                                />
                                                <span
                                                    class="text-[11px] text-muted-foreground"
                                                    >Remove</span
                                                >
                                            </button>
                                            <div
                                                v-if="labels.length"
                                                class="my-1 border-b border-border"
                                            />
                                            <button
                                                v-for="l in filteredAvailableLabels"
                                                :key="`al-${l.id}`"
                                                type="button"
                                                class="flex w-full items-center gap-2 px-2 py-1 text-left text-[12.5px] hover:bg-accent"
                                                @click="attachLabel(l.id)"
                                            >
                                                <LabelBadge
                                                    :name="l.name"
                                                    :color="l.color"
                                                />
                                            </button>
                                            <button
                                                v-if="
                                                    labelQuery.trim() &&
                                                    !exactLabelMatch
                                                "
                                                type="button"
                                                :disabled="
                                                    labelCreating ||
                                                    !project.teams.length
                                                "
                                                class="flex w-full items-center gap-2 border-t border-border px-2 py-1.5 text-left text-[12.5px] text-foreground hover:bg-accent disabled:opacity-50"
                                                @click="quickCreateLabel"
                                            >
                                                <Plus class="size-3.5" />
                                                <span>
                                                    Create label
                                                    <span class="font-medium"
                                                        >“{{
                                                            labelQuery.trim()
                                                        }}”</span
                                                    >
                                                </span>
                                            </button>
                                            <p
                                                v-if="
                                                    !labels.length &&
                                                    !filteredAvailableLabels.length &&
                                                    !labelQuery.trim()
                                                "
                                                class="px-2 py-2 text-[12px] text-muted-foreground"
                                            >
                                                No labels yet — type a name to
                                                create one.
                                            </p>
                                        </div>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </dd>
                        </dl>
                    </section>

                    <!-- ============== MILESTONES ============== -->
                    <section
                        class="rounded-lg border border-border/60 bg-card/40 px-3 py-2.5"
                    >
                        <header class="mb-2 flex items-center justify-between">
                            <button
                                type="button"
                                class="flex items-center gap-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase hover:text-foreground"
                                :aria-expanded="!sectionCollapsed.milestones"
                                @click="toggleSection('milestones')"
                            >
                                Milestones
                                <component
                                    :is="
                                        sectionCollapsed.milestones
                                            ? ChevronRight
                                            : ChevronDown
                                    "
                                    class="size-3"
                                />
                            </button>
                            <button
                                type="button"
                                class="rounded p-0.5 text-muted-foreground hover:bg-accent hover:text-foreground"
                                aria-label="Add milestone"
                                title="Add milestone"
                                @click="openMilestoneDialog"
                            >
                                <Plus class="size-3" />
                            </button>
                        </header>

                        <ul
                            v-if="
                                project.milestones.length &&
                                !sectionCollapsed.milestones
                            "
                            class="space-y-1.5"
                        >
                            <li
                                v-for="ms in project.milestones"
                                :key="ms.id"
                                class="flex items-center gap-2 rounded-md px-1 py-1 hover:bg-accent/40"
                            >
                                <Diamond
                                    class="size-3 shrink-0"
                                    :style="{
                                        color: project.color || '#6366f1',
                                        fill: project.color || '#6366f1',
                                    }"
                                />
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="truncate text-[12.5px] font-medium text-foreground"
                                    >
                                        {{ ms.name }}
                                    </div>
                                    <div
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        {{ ms.percent }}% of
                                        {{ ms.issue_count }}
                                    </div>
                                </div>
                                <span
                                    v-if="ms.target_date"
                                    class="shrink-0 rounded-md border border-border bg-card px-1.5 py-px text-[11px] text-muted-foreground tabular-nums"
                                >
                                    {{ fmtShort(ms.target_date) }}
                                </span>
                            </li>
                        </ul>

                        <p
                            v-else-if="!sectionCollapsed.milestones"
                            class="text-[12.5px] leading-[18px] text-muted-foreground"
                        >
                            Add milestones to organize work within your project
                            and break it into more granular stages.
                        </p>
                    </section>

                    <!-- ============== PROGRESS ============== -->
                    <section
                        class="rounded-lg border border-border/60 bg-card/40 px-3 py-2.5"
                    >
                        <header class="mb-2 flex items-center justify-between">
                            <button
                                type="button"
                                class="flex items-center gap-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase hover:text-foreground"
                                :aria-expanded="!sectionCollapsed.progress"
                                @click="toggleSection('progress')"
                            >
                                Progress
                                <component
                                    :is="
                                        sectionCollapsed.progress
                                            ? ChevronRight
                                            : ChevronDown
                                    "
                                    class="size-3"
                                />
                            </button>
                        </header>

                        <div v-show="!sectionCollapsed.progress">
                            <!-- 3 stat cards -->
                            <div class="mb-3 grid grid-cols-3 gap-2">
                                <div
                                    class="rounded-md border border-border bg-card p-2"
                                >
                                    <div
                                        class="flex items-center gap-1.5 text-[11px] text-muted-foreground"
                                    >
                                        <span
                                            class="size-1.5 rounded-sm bg-zinc-500"
                                        ></span>
                                        Scope
                                    </div>
                                    <div
                                        class="mt-1 text-[16px] font-semibold tabular-nums"
                                    >
                                        {{ progress.total }}
                                    </div>
                                </div>
                                <div
                                    class="rounded-md border border-border bg-card p-2"
                                >
                                    <div
                                        class="flex items-center gap-1.5 text-[11px] text-muted-foreground"
                                    >
                                        <span
                                            class="size-1.5 rounded-sm bg-amber-400"
                                        ></span>
                                        Started
                                    </div>
                                    <div
                                        class="mt-1 text-[16px] font-semibold tabular-nums"
                                    >
                                        {{ progress.started }}
                                    </div>
                                </div>
                                <div
                                    class="rounded-md border border-border bg-card p-2"
                                >
                                    <div
                                        class="flex items-center gap-1.5 text-[11px] text-muted-foreground"
                                    >
                                        <span
                                            class="size-1.5 rounded-sm bg-indigo-500"
                                        ></span>
                                        Done
                                    </div>
                                    <div
                                        class="mt-1 text-[16px] font-semibold tabular-nums"
                                    >
                                        {{ progress.completed }}
                                    </div>
                                </div>
                            </div>

                            <!-- Burndown chart -->
                            <div
                                class="relative h-[160px] rounded-md border border-border bg-card"
                            >
                                <svg
                                    viewBox="0 0 256 140"
                                    preserveAspectRatio="none"
                                    class="absolute inset-0 h-full w-full"
                                    aria-hidden="true"
                                >
                                    <line
                                        x1="12"
                                        y1="18"
                                        x2="244"
                                        y2="18"
                                        stroke="currentColor"
                                        stroke-width="0.5"
                                        class="text-border"
                                    />
                                    <line
                                        x1="12"
                                        y1="70"
                                        x2="244"
                                        y2="70"
                                        stroke="currentColor"
                                        stroke-width="0.5"
                                        class="text-border"
                                        stroke-dasharray="2 3"
                                    />
                                    <line
                                        x1="12"
                                        y1="122"
                                        x2="244"
                                        y2="122"
                                        stroke="currentColor"
                                        stroke-width="0.5"
                                        class="text-border"
                                    />
                                    <polyline
                                        :points="burndownIdealPoints"
                                        fill="none"
                                        stroke="#71717a"
                                        stroke-width="1"
                                        stroke-dasharray="3 3"
                                    />
                                    <polyline
                                        :points="burndownActualPoints"
                                        fill="none"
                                        stroke="#6366f1"
                                        stroke-width="1.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                                <div
                                    class="pointer-events-none absolute inset-x-2 bottom-1 flex items-center justify-between text-[10px] text-muted-foreground tabular-nums"
                                >
                                    <span>{{
                                        project.start_date
                                            ? fmtShort(project.start_date)
                                            : ''
                                    }}</span>
                                    <span>{{
                                        project.target_date
                                            ? fmtShort(project.target_date)
                                            : ''
                                    }}</span>
                                </div>
                            </div>

                            <!-- Pill tabs -->
                            <div class="mt-3 flex items-center gap-1">
                                <button
                                    v-for="t in [
                                        'assignees',
                                        'labels',
                                        'cycles',
                                    ] as const"
                                    :key="t"
                                    type="button"
                                    @click="progressTab = t"
                                    :class="[
                                        'rounded-md px-2 py-1 text-[12px] capitalize transition-colors',
                                        progressTab === t
                                            ? 'bg-accent text-foreground'
                                            : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                                    ]"
                                >
                                    {{ t }}
                                </button>
                            </div>

                            <div class="mt-2">
                                <ul
                                    v-if="progressTab === 'assignees'"
                                    class="space-y-1.5"
                                >
                                    <li
                                        v-if="!assignees.length"
                                        class="text-[12.5px] text-muted-foreground"
                                    >
                                        No assignees yet.
                                    </li>
                                    <li
                                        v-for="(row, i) in assignees"
                                        :key="row.user?.id ?? `none-${i}`"
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <div
                                            class="flex min-w-0 items-center gap-1.5"
                                        >
                                            <Avatar
                                                v-if="row.user"
                                                :name="row.user.name"
                                                :email="row.user.email"
                                                :size="18"
                                            />
                                            <span
                                                v-else
                                                class="size-[18px] rounded-full border border-dashed border-border"
                                            ></span>
                                            <span
                                                class="truncate text-[12.5px] text-foreground"
                                            >
                                                {{
                                                    row.user
                                                        ? row.user.name
                                                        : 'Unassigned'
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            class="flex shrink-0 items-center gap-1.5"
                                        >
                                            <span
                                                class="text-[11px] text-muted-foreground tabular-nums"
                                            >
                                                {{ row.percent }}% of
                                                {{ row.total }}
                                            </span>
                                            <svg
                                                width="14"
                                                height="14"
                                                viewBox="0 0 14 14"
                                                fill="none"
                                            >
                                                <circle
                                                    cx="7"
                                                    cy="7"
                                                    r="5"
                                                    stroke="#3f3f46"
                                                    stroke-width="1.5"
                                                    fill="none"
                                                />
                                                <circle
                                                    cx="7"
                                                    cy="7"
                                                    r="5"
                                                    fill="none"
                                                    stroke-width="2"
                                                    :stroke="
                                                        donutStroke(row.percent)
                                                    "
                                                    :stroke-dasharray="`${donutC} ${donutC}`"
                                                    :stroke-dashoffset="
                                                        donutOffset(row.percent)
                                                    "
                                                    transform="rotate(-90 7 7)"
                                                />
                                            </svg>
                                        </div>
                                    </li>
                                </ul>
                                <p
                                    v-else
                                    class="text-[12.5px] text-muted-foreground"
                                >
                                    Coming soon
                                </p>
                            </div>

                            <div
                                class="mt-3 flex items-center justify-between gap-2 border-t border-border pt-2"
                            >
                                <span class="text-[12px] text-muted-foreground"
                                    >{{ progress.percent }}% complete</span
                                >
                                <svg
                                    width="14"
                                    height="14"
                                    viewBox="0 0 14 14"
                                    fill="none"
                                >
                                    <circle
                                        cx="7"
                                        cy="7"
                                        r="5"
                                        stroke="#3f3f46"
                                        stroke-width="1.5"
                                        fill="none"
                                    />
                                    <circle
                                        cx="7"
                                        cy="7"
                                        r="5"
                                        fill="none"
                                        stroke-width="2"
                                        :stroke="ringStroke"
                                        :stroke-dasharray="`${ringC} ${ringC}`"
                                        :stroke-dashoffset="ringDashOffset"
                                        transform="rotate(-90 7 7)"
                                    />
                                </svg>
                            </div>
                        </div>
                    </section>
                </div>
            </aside>
        </div>

        <!-- New milestone dialog -->
        <Dialog v-model:open="milestoneDialogOpen">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle>New milestone</DialogTitle>
                    <DialogDescription>
                        Break the project into milestones to track progress in
                        stages.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitMilestone">
                    <div class="space-y-1">
                        <label
                            class="text-[12px] font-medium text-foreground"
                            for="ms-name"
                            >Name</label
                        >
                        <Input
                            id="ms-name"
                            v-model="milestoneForm.name"
                            type="text"
                            placeholder="e.g. v1 launch"
                            class="h-8 text-[13px]"
                            autofocus
                        />
                    </div>
                    <div class="space-y-1">
                        <label
                            class="text-[12px] font-medium text-foreground"
                            for="ms-desc"
                            >Description</label
                        >
                        <textarea
                            id="ms-desc"
                            v-model="milestoneForm.description"
                            rows="3"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-[13px] outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50"
                            placeholder="What is delivered at this milestone?"
                        />
                    </div>
                    <div class="space-y-1">
                        <label
                            class="text-[12px] font-medium text-foreground"
                            for="ms-target"
                            >Target date</label
                        >
                        <input
                            id="ms-target"
                            v-model="milestoneForm.target_date"
                            type="date"
                            class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-[13px]"
                        />
                    </div>
                    <p v-if="milestoneError" class="text-[12px] text-rose-400">
                        {{ milestoneError }}
                    </p>
                    <DialogFooter>
                        <DialogClose
                            class="rounded-md px-3 py-1.5 text-[13px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        >
                            Cancel
                        </DialogClose>
                        <button
                            type="submit"
                            :disabled="milestoneSubmitting"
                            class="inline-flex items-center gap-1.5 rounded-md bg-foreground px-3 py-1.5 text-[13px] font-medium text-background transition-opacity hover:opacity-90 disabled:opacity-50"
                        >
                            <Loader2
                                v-if="milestoneSubmitting"
                                class="size-3.5 animate-spin"
                                aria-hidden="true"
                            />
                            {{
                                milestoneSubmitting
                                    ? 'Creating…'
                                    : 'Create milestone'
                            }}
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete project confirm dialog -->
        <Dialog v-model:open="deleteDialogOpen">
            <DialogContent class="sm:max-w-[460px]">
                <DialogHeader>
                    <DialogTitle>Delete project?</DialogTitle>
                    <DialogDescription>
                        <strong class="text-foreground">{{
                            project.name
                        }}</strong>
                        and all of its issues, milestones and activity will be
                        moved to Trash. You can restore them later.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose
                        class="rounded-md px-3 py-1.5 text-[13px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    >
                        Cancel
                    </DialogClose>
                    <button
                        type="button"
                        :disabled="deleteSubmitting"
                        class="inline-flex items-center gap-1.5 rounded-md bg-rose-500/90 px-3 py-1.5 text-[13px] font-medium text-white transition-opacity hover:opacity-90 disabled:opacity-50"
                        @click="confirmDelete"
                    >
                        <Loader2
                            v-if="deleteSubmitting"
                            class="size-3.5 animate-spin"
                            aria-hidden="true"
                        />
                        <Trash2 v-else class="size-3.5" aria-hidden="true" />
                        {{ deleteSubmitting ? 'Deleting…' : 'Move to Trash' }}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
