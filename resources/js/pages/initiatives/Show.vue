<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';
import {
    Calendar,
    ChevronDown,
    ChevronRight,
    Diamond,
    MoreHorizontal,
    PanelRightClose,
    PanelRightOpen,
    Plus,
} from 'lucide-vue-next';
import Avatar from '@/components/repo/Avatar.vue';
import ProjectIcon from '@/components/repo/ProjectIcon.vue';
import InitiativeIcon from '@/components/repo/initiatives/InitiativeIcon.vue';
import { renderMarkdown } from '@/lib/markdown';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type Member = { id: number; name: string; email: string };
type ProjectRow = {
    id: number;
    name: string;
    slug: string;
    state: string | null;
    color: string | null;
    icon: string | null;
    target_date: string | null;
    lead: { id: number; name: string; email: string } | null;
    total_issues: number;
    completed_issues: number;
    progress: number;
};
type AvailableProject = {
    id: number;
    name: string;
    slug: string;
    color: string | null;
    icon: string | null;
    state: string | null;
};
type Initiative = {
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
    owner: { id: number; name: string; email: string } | null;
    parent: { id: number; name: string; slug: string } | null;
    children: Array<{
        id: number;
        name: string;
        slug: string;
        state: string | null;
        color: string | null;
        icon: string | null;
    }>;
    members: Array<{
        id: number;
        name: string;
        email: string;
        role: string | null;
    }>;
};

const props = defineProps<{
    initiative: Initiative;
    projects: ProjectRow[];
    available_projects: AvailableProject[];
    members: Member[];
    progress: {
        total_projects: number;
        total_issues: number;
        completed_issues: number;
        percent: number;
    };
    tab: 'overview' | 'projects' | 'activity';
}>();

const STATE_LABELS: Record<string, string> = {
    planned: 'Planned',
    active: 'Active',
    completed: 'Completed',
    canceled: 'Canceled',
};
const STATE_ORDER = ['planned', 'active', 'completed', 'canceled'];

const descriptionHtml = computed<string>(() =>
    renderMarkdown(props.initiative.description),
);

function fmtDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
function fmtShort(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

function tabHref(tab: 'overview' | 'projects' | 'activity') {
    return tab === 'overview'
        ? `/initiatives/${props.initiative.slug}`
        : `/initiatives/${props.initiative.slug}?tab=${tab}`;
}

const ringStroke = computed(() => {
    if (props.initiative.state === 'canceled') return '#a1a1aa';
    if (props.progress.percent >= 100 || props.initiative.state === 'completed')
        return '#10b981';
    if (props.progress.percent > 0) return '#f59e0b';
    return '#a1a1aa';
});

// =================================================================
// Mutations
// =================================================================
function patchInitiative(payload: Record<string, FormDataConvertible>) {
    router.patch(`/initiatives/${props.initiative.slug}`, payload, {
        preserveScroll: true,
        preserveState: false,
    });
}

function setState(state: string) {
    if (state === props.initiative.state) return;
    patchInitiative({ state });
}
function setOwner(userId: number | null) {
    if ((props.initiative.owner?.id ?? null) === userId) return;
    patchInitiative({ owner_user_id: userId });
}
function setStartDate(date: string) {
    patchInitiative({ start_date: date === '' ? null : date });
}
function setTargetDate(date: string) {
    patchInitiative({ target_date: date === '' ? null : date });
}

// ---- Right rail collapse ----
const RAIL_KEY = 'aims:initiative-rail';
const railCollapsed = ref<boolean>(false);
const sectionCollapsed = ref<Record<string, boolean>>({
    properties: false,
    progress: false,
    members: false,
});
function toggleRail() {
    railCollapsed.value = !railCollapsed.value;
    persistRailState();
}
function toggleSection(key: 'properties' | 'progress' | 'members') {
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

// ---- Inline editing: name + description ----
const editingName = ref<boolean>(false);
const nameDraft = ref<string>('');
const nameInput = ref<HTMLInputElement | null>(null);

function startEditName() {
    nameDraft.value = props.initiative.name;
    editingName.value = true;
    nextTick(() => {
        nameInput.value?.focus();
        nameInput.value?.select();
    });
}
function commitName() {
    const v = nameDraft.value.trim();
    editingName.value = false;
    if (v === '' || v === props.initiative.name) return;
    patchInitiative({ name: v });
}
function cancelName() {
    editingName.value = false;
}

const editingDesc = ref<boolean>(false);
const descDraft = ref<string>('');
const descTextarea = ref<HTMLTextAreaElement | null>(null);

function startEditDesc() {
    descDraft.value = props.initiative.description ?? '';
    editingDesc.value = true;
    nextTick(() => {
        descTextarea.value?.focus();
    });
}
function commitDesc() {
    editingDesc.value = false;
    if ((descDraft.value ?? '') === (props.initiative.description ?? ''))
        return;
    patchInitiative({
        description: descDraft.value === '' ? null : descDraft.value,
    });
}
function cancelDesc() {
    editingDesc.value = false;
}

// ---- Project attach / detach ----
function attachProject(projectId: number) {
    router.post(
        `/initiatives/${props.initiative.slug}/projects`,
        { project_id: projectId },
        { preserveScroll: true, preserveState: false },
    );
}
function detachProject(projectId: number) {
    router.delete(
        `/initiatives/${props.initiative.slug}/projects/${projectId}`,
        { preserveScroll: true, preserveState: false },
    );
}
</script>

<template>
    <Head :title="`${initiative.name} · Initiative`" />

    <div class="flex h-full flex-1 flex-col overflow-hidden">
        <!-- Header -->
        <header
            class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4 py-2.5"
        >
            <div
                class="flex min-w-0 items-center gap-2 text-[12.5px] text-muted-foreground"
            >
                <Link href="/initiatives" class="hover:text-foreground">
                    Initiatives
                </Link>
                <span>/</span>
                <span class="truncate text-foreground">{{
                    initiative.name
                }}</span>
            </div>
            <button
                type="button"
                class="rounded-md p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground"
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
        </header>

        <!-- Tabs -->
        <div
            class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4"
        >
            <nav class="flex items-center gap-1 py-2 text-[12.5px]">
                <Link
                    v-for="t in ['overview', 'projects', 'activity'] as const"
                    :key="t"
                    :href="tabHref(t)"
                    :class="[
                        'rounded-md px-2 py-1 capitalize transition-colors',
                        tab === t
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                    ]"
                >
                    {{ t }}
                </Link>
            </nav>
        </div>

        <!-- Body -->
        <div class="flex flex-1 overflow-hidden">
            <main class="flex-1 overflow-y-auto px-6 py-5">
                <!-- Overview tab -->
                <section
                    v-if="tab === 'overview'"
                    class="mx-auto max-w-3xl space-y-6"
                >
                    <div class="flex items-start gap-3">
                        <InitiativeIcon
                            :icon="initiative.icon"
                            :color="initiative.color"
                            :size="40"
                            rounded="lg"
                        />
                        <div class="min-w-0 flex-1">
                            <input
                                v-if="editingName"
                                ref="nameInput"
                                v-model="nameDraft"
                                type="text"
                                class="w-full bg-transparent text-[22px] leading-tight font-semibold text-foreground outline-none"
                                @keydown.enter.prevent="commitName"
                                @keydown.escape="cancelName"
                                @blur="commitName"
                            />
                            <h1
                                v-else
                                class="cursor-text text-[22px] leading-tight font-semibold text-foreground"
                                @click="startEditName"
                            >
                                {{ initiative.name }}
                            </h1>
                            <div
                                class="mt-1 flex flex-wrap items-center gap-2 text-[12px] text-muted-foreground"
                            >
                                <span
                                    class="rounded-full bg-muted px-1.5 py-0.5 text-[10px] tracking-wide uppercase"
                                >
                                    {{
                                        STATE_LABELS[
                                            initiative.state ?? 'planned'
                                        ]
                                    }}
                                </span>
                                <span
                                    v-if="initiative.target_date"
                                    class="inline-flex items-center gap-1"
                                >
                                    <Calendar class="size-3.5" />
                                    {{ fmtDate(initiative.target_date) }}
                                </span>
                                <span v-if="initiative.parent">
                                    Sub-initiative of
                                    <Link
                                        :href="`/initiatives/${initiative.parent.slug}`"
                                        class="text-foreground hover:underline"
                                    >
                                        {{ initiative.parent.name }}
                                    </Link>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div
                            v-if="!editingDesc"
                            class="prose prose-invert max-w-none cursor-text rounded-md px-2 py-2 text-[13.5px] hover:bg-accent/30"
                            @click="startEditDesc"
                        >
                            <div
                                v-if="initiative.description"
                                v-html="descriptionHtml"
                            />
                            <p v-else class="text-muted-foreground">
                                Click to add a description…
                            </p>
                        </div>
                        <div v-else class="space-y-2">
                            <textarea
                                ref="descTextarea"
                                v-model="descDraft"
                                rows="6"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-[13.5px] outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                placeholder="Describe the goal, success metrics, scope…"
                                @keydown.escape="cancelDesc"
                            />
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="rounded-md bg-foreground px-3 py-1 text-[12.5px] font-medium text-background hover:opacity-90"
                                    @click="commitDesc"
                                >
                                    Save
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md px-3 py-1 text-[12.5px] text-muted-foreground hover:bg-accent hover:text-foreground"
                                    @click="cancelDesc"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Sub-initiatives -->
                    <div v-if="initiative.children.length" class="space-y-2">
                        <h3
                            class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Sub-initiatives
                        </h3>
                        <ul
                            class="divide-y divide-border rounded-md border border-border"
                        >
                            <li v-for="c in initiative.children" :key="c.id">
                                <Link
                                    :href="`/initiatives/${c.slug}`"
                                    class="flex items-center gap-2 px-3 py-2 text-[13px] hover:bg-accent/40"
                                >
                                    <InitiativeIcon
                                        :icon="c.icon"
                                        :color="c.color"
                                        :size="16"
                                    />
                                    <span class="truncate">{{ c.name }}</span>
                                    <span
                                        class="ml-auto rounded-full bg-muted px-1.5 py-0.5 text-[10px] tracking-wide text-muted-foreground uppercase"
                                    >
                                        {{ STATE_LABELS[c.state ?? 'planned'] }}
                                    </span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                </section>

                <!-- Projects tab -->
                <section
                    v-else-if="tab === 'projects'"
                    class="mx-auto max-w-4xl space-y-4"
                >
                    <div class="flex items-center justify-between">
                        <h2 class="text-[14px] font-medium text-foreground">
                            Projects
                            <span
                                class="ml-1 text-[12px] text-muted-foreground tabular-nums"
                            >
                                {{ projects.length }}
                            </span>
                        </h2>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-[12.5px] text-foreground transition-colors hover:bg-accent"
                                >
                                    <Plus class="size-3.5" />
                                    Add project
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="end"
                                class="max-h-72 w-64 overflow-y-auto"
                            >
                                <DropdownMenuLabel
                                    >Attach project</DropdownMenuLabel
                                >
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    v-for="p in available_projects"
                                    :key="p.id"
                                    @select="attachProject(p.id)"
                                >
                                    <span
                                        class="flex min-w-0 items-center gap-2"
                                    >
                                        <ProjectIcon
                                            :icon="p.icon"
                                            :color="p.color"
                                            :size="14"
                                        />
                                        <span class="truncate">{{
                                            p.name
                                        }}</span>
                                    </span>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="!available_projects.length"
                                    disabled
                                >
                                    No projects to attach
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    <div
                        v-if="!projects.length"
                        class="rounded-md border border-dashed border-border px-6 py-12 text-center"
                    >
                        <p class="text-sm text-muted-foreground">
                            No projects attached to this initiative yet.
                        </p>
                    </div>

                    <ul
                        v-else
                        class="divide-y divide-border rounded-md border border-border"
                    >
                        <li
                            v-for="p in projects"
                            :key="p.id"
                            class="group/row flex items-center gap-3 px-3 py-2 hover:bg-accent/30"
                        >
                            <Link
                                :href="`/projects/${p.slug}`"
                                class="flex min-w-0 flex-1 items-center gap-3"
                            >
                                <ProjectIcon
                                    :icon="p.icon"
                                    :color="p.color"
                                    :size="18"
                                />
                                <span
                                    class="truncate text-[13px] text-foreground"
                                    >{{ p.name }}</span
                                >
                            </Link>
                            <span
                                v-if="p.target_date"
                                class="hidden shrink-0 items-center gap-1 text-[12px] text-muted-foreground sm:inline-flex"
                            >
                                <Calendar class="size-3.5" />
                                {{ fmtShort(p.target_date) }}
                            </span>
                            <Avatar
                                v-if="p.lead"
                                :name="p.lead.name"
                                :email="p.lead.email"
                                :size="18"
                            />
                            <span
                                class="shrink-0 text-right text-[12px] text-muted-foreground tabular-nums"
                            >
                                {{ p.completed_issues }}/{{ p.total_issues }}
                            </span>
                            <span
                                class="shrink-0 text-right text-[12px] text-foreground tabular-nums"
                            >
                                {{ p.progress }}%
                            </span>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <button
                                        type="button"
                                        class="rounded p-1 text-muted-foreground opacity-0 group-hover/row:opacity-100 hover:bg-accent hover:text-foreground"
                                        aria-label="Project menu"
                                    >
                                        <MoreHorizontal class="size-3.5" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        @select="detachProject(p.id)"
                                    >
                                        Remove from initiative
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </li>
                    </ul>
                </section>

                <!-- Activity tab (placeholder) -->
                <section v-else class="mx-auto max-w-3xl">
                    <div
                        class="rounded-md border border-dashed border-border px-6 py-12 text-center"
                    >
                        <p class="text-sm text-muted-foreground">
                            Activity stream coming soon.
                        </p>
                    </div>
                </section>
            </main>

            <!-- Right rail -->
            <aside
                v-if="!railCollapsed"
                class="hidden w-72 shrink-0 overflow-y-auto border-l border-border px-4 py-5 lg:block"
            >
                <button
                    type="button"
                    class="mb-3 flex w-full items-center gap-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase hover:text-foreground"
                    :aria-expanded="!sectionCollapsed.properties"
                    @click="toggleSection('properties')"
                >
                    <component
                        :is="
                            sectionCollapsed.properties
                                ? ChevronRight
                                : ChevronDown
                        "
                        class="size-3"
                    />
                    Properties
                </button>
                <div
                    v-show="!sectionCollapsed.properties"
                    class="space-y-2 text-[12.5px]"
                >
                    <!-- Status -->
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Status</span>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button
                                    class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-foreground hover:bg-accent"
                                >
                                    <Diamond
                                        class="size-3"
                                        :style="{
                                            color:
                                                initiative.color || '#6366f1',
                                        }"
                                    />
                                    {{
                                        STATE_LABELS[
                                            initiative.state ?? 'planned'
                                        ]
                                    }}
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    v-for="s in STATE_ORDER"
                                    :key="s"
                                    @select="setState(s)"
                                >
                                    {{ STATE_LABELS[s] }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                    <!-- Owner -->
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Owner</span>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button
                                    class="inline-flex items-center gap-1.5 rounded px-1.5 py-0.5 hover:bg-accent"
                                >
                                    <template v-if="initiative.owner">
                                        <Avatar
                                            :name="initiative.owner.name"
                                            :email="initiative.owner.email"
                                            :size="16"
                                        />
                                        <span class="text-foreground">{{
                                            initiative.owner.name
                                        }}</span>
                                    </template>
                                    <span v-else class="text-muted-foreground"
                                        >No owner</span
                                    >
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="end"
                                class="max-h-72 w-56 overflow-y-auto"
                            >
                                <DropdownMenuItem @select="setOwner(null)"
                                    >No owner</DropdownMenuItem
                                >
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    v-for="m in members"
                                    :key="m.id"
                                    @select="setOwner(m.id)"
                                >
                                    <span class="flex items-center gap-2">
                                        <Avatar
                                            :name="m.name"
                                            :email="m.email"
                                            :size="14"
                                        />
                                        {{ m.name }}
                                    </span>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                    <!-- Dates -->
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Start date</span>
                        <input
                            type="date"
                            :value="initiative.start_date ?? ''"
                            class="rounded border border-input bg-transparent px-1.5 py-0.5 text-[12px]"
                            @change="
                                (e) =>
                                    setStartDate(
                                        (e.target as HTMLInputElement).value,
                                    )
                            "
                        />
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Target date</span>
                        <input
                            type="date"
                            :value="initiative.target_date ?? ''"
                            class="rounded border border-input bg-transparent px-1.5 py-0.5 text-[12px]"
                            @change="
                                (e) =>
                                    setTargetDate(
                                        (e.target as HTMLInputElement).value,
                                    )
                            "
                        />
                    </div>
                    <div
                        v-if="initiative.parent"
                        class="flex items-center justify-between"
                    >
                        <span class="text-muted-foreground">Parent</span>
                        <Link
                            :href="`/initiatives/${initiative.parent.slug}`"
                            class="text-foreground hover:underline"
                        >
                            {{ initiative.parent.name }}
                        </Link>
                    </div>
                </div>

                <button
                    type="button"
                    class="mt-6 mb-3 flex w-full items-center gap-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase hover:text-foreground"
                    :aria-expanded="!sectionCollapsed.progress"
                    @click="toggleSection('progress')"
                >
                    <component
                        :is="
                            sectionCollapsed.progress
                                ? ChevronRight
                                : ChevronDown
                        "
                        class="size-3"
                    />
                    Progress
                </button>
                <div
                    v-show="!sectionCollapsed.progress"
                    class="space-y-2 text-[12.5px]"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Projects</span>
                        <span class="text-foreground tabular-nums">{{
                            progress.total_projects
                        }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Issues</span>
                        <span class="text-foreground tabular-nums"
                            >{{ progress.completed_issues }}/{{
                                progress.total_issues
                            }}</span
                        >
                    </div>
                    <div class="flex items-center gap-2">
                        <div
                            class="flex-1 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-1.5"
                                :style="{
                                    width: `${progress.percent}%`,
                                    backgroundColor: ringStroke,
                                }"
                            ></div>
                        </div>
                        <span class="text-foreground tabular-nums"
                            >{{ progress.percent }}%</span
                        >
                    </div>
                </div>

                <div v-if="initiative.members.length" class="mt-6">
                    <button
                        type="button"
                        class="mb-3 flex w-full items-center gap-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase hover:text-foreground"
                        :aria-expanded="!sectionCollapsed.members"
                        @click="toggleSection('members')"
                    >
                        <component
                            :is="
                                sectionCollapsed.members
                                    ? ChevronRight
                                    : ChevronDown
                            "
                            class="size-3"
                        />
                        Members
                    </button>
                    <ul v-show="!sectionCollapsed.members" class="space-y-1.5">
                        <li
                            v-for="m in initiative.members"
                            :key="m.id ?? 0"
                            class="flex items-center gap-2 text-[12.5px]"
                        >
                            <Avatar
                                :name="m.name"
                                :email="m.email"
                                :size="16"
                            />
                            <span class="truncate text-foreground">{{
                                m.name
                            }}</span>
                            <span
                                class="ml-auto text-[11px] text-muted-foreground"
                                >{{ m.role }}</span
                            >
                        </li>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</template>
