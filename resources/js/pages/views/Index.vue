<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';
import { Layers, Plus, Star, MoreHorizontal } from 'lucide-vue-next';
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

type Owner = { id: number; name: string; email: string };
type ViewTeam = { id: number; name: string; key: string; color: string | null };
type ViewItem = {
    id: number;
    name: string;
    description: string | null;
    scope: 'personal' | 'team' | 'workspace' | null;
    team: ViewTeam | null;
    filters: Record<string, unknown>;
    grouping: string;
    sorting: string;
    is_favorite: boolean;
    is_owner: boolean;
    owner: Owner | null;
    updated_at: string | null;
};
type WorkspaceTeam = { id: number; name: string; key: string; color: string | null };

const props = defineProps<{
    views: ViewItem[];
    teams: WorkspaceTeam[];
    filters: { scope: string | null; team: string | null };
    scopes: Record<string, string>;
}>();

const SCOPE_LABEL: Record<string, string> = {
    personal: 'Personal',
    team: 'Team',
    workspace: 'Workspace',
};

const grouped = computed(() => {
    const groups: Record<string, ViewItem[]> = { personal: [], team: [], workspace: [] };
    for (const v of props.views) {
        const key = v.scope ?? 'personal';
        if (!groups[key]) groups[key] = [];
        groups[key].push(v);
    }
    return [
        { key: 'personal', label: 'Personal', items: groups.personal },
        { key: 'team', label: 'Team', items: groups.team },
        { key: 'workspace', label: 'Workspace', items: groups.workspace },
    ].filter((g) => g.items.length > 0);
});

function fmtShort(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

function toggleFavorite(view: ViewItem, e: Event) {
    e.preventDefault();
    e.stopPropagation();
    router.post(`/views/${view.id}/favorite`, {}, {
        preserveScroll: true,
        preserveState: false,
    });
}

function deleteView(view: ViewItem) {
    if (typeof window !== 'undefined') {
        if (!window.confirm(`Delete view "${view.name}"?`)) return;
    }
    router.delete(`/views/${view.id}`, {
        preserveScroll: true,
        preserveState: false,
    });
}

// ---- New view dialog ----
const newDialogOpen = ref<boolean>(false);
const newForm = ref<{
    name: string;
    description: string;
    scope: 'personal' | 'team' | 'workspace';
    team_key: string;
    filters: Record<string, unknown>;
    grouping: string;
    sorting: string;
}>({
    name: '',
    description: '',
    scope: 'personal',
    team_key: '',
    filters: {},
    grouping: 'status',
    sorting: 'priority',
});
const newSubmitting = ref<boolean>(false);
const newError = ref<string | null>(null);

function openNew() {
    newForm.value = {
        name: '',
        description: '',
        scope: 'personal',
        team_key: '',
        filters: {},
        grouping: 'status',
        sorting: 'priority',
    };
    newError.value = null;
    newDialogOpen.value = true;
}

function submitNew() {
    if (!newForm.value.name.trim()) {
        newError.value = 'Name is required.';
        return;
    }
    newSubmitting.value = true;
    newError.value = null;
    const payload: Record<string, FormDataConvertible> = {
        name: newForm.value.name.trim(),
        scope: newForm.value.scope,
        grouping: newForm.value.grouping,
        sorting: newForm.value.sorting,
    };
    if (newForm.value.description.trim() !== '') {
        payload.description = newForm.value.description.trim();
    }
    if (newForm.value.scope === 'team' && newForm.value.team_key !== '') {
        payload.team_key = newForm.value.team_key;
    }
    // filters as JSON-stringified array entries (Inertia handles arrays/objects directly)
    const filterEntries = Object.entries(newForm.value.filters);
    if (filterEntries.length > 0) {
        for (const [k, v] of filterEntries) {
            if (v === null || v === undefined || v === '') continue;
            payload[`filters[${k}]`] = Array.isArray(v) ? v.join(',') : String(v);
        }
    }
    router.post('/views', payload, {
        onSuccess: () => {
            newDialogOpen.value = false;
            newSubmitting.value = false;
        },
        onError: (errors) => {
            newSubmitting.value = false;
            const first = Object.values(errors)[0];
            newError.value = (first as string | undefined) ?? 'Could not create view.';
        },
        onFinish: () => {
            newSubmitting.value = false;
        },
    });
}

function setScope(value: string | null) {
    const params: Record<string, string> = {};
    if (value) params.scope = value;
    if (props.filters.team) params.team = props.filters.team;
    router.get('/views', params, { preserveState: false });
}

// On mount: if URL has filter context (?from=issues&team=X), pre-fill new dialog.
const presetFromIssues = ref<Record<string, string>>({});
onMounted(() => {
    if (typeof window === 'undefined') return;
    const url = new URL(window.location.href);
    const data: Record<string, string> = {};
    for (const k of ['team', 'assignee', 'state', 'priority', 'project', 'labels', 'group', 'sort']) {
        const v = url.searchParams.get(k);
        if (v !== null && v !== '') data[k] = v;
    }
    presetFromIssues.value = data;
});

function openNewWithPreset() {
    openNew();
    const data = presetFromIssues.value;
    if (data.team) {
        newForm.value.team_key = data.team;
        newForm.value.scope = 'team';
    }
    if (data.group) newForm.value.grouping = data.group;
    if (data.sort) newForm.value.sorting = data.sort;
    const filters: Record<string, unknown> = {};
    if (data.team) filters.team = data.team;
    for (const k of ['assignee', 'state'] as const) if (data[k]) filters[k] = data[k];
    if (data.priority) filters.priority = parseInt(data.priority, 10);
    if (data.project) filters.project = parseInt(data.project, 10);
    if (data.labels) filters.labels = data.labels.split(',').map((s) => parseInt(s, 10)).filter((n) => Number.isFinite(n));
    newForm.value.filters = filters;
}

defineExpose({ openNewWithPreset });
</script>

<template>
    <Head title="Views" />

    <div class="flex h-full flex-1 flex-col overflow-hidden">
        <header
            class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4 py-2.5"
        >
            <div class="flex min-w-0 items-center gap-2">
                <Layers class="size-4 text-muted-foreground" />
                <h1 class="text-[13px] font-medium">Views</h1>
            </div>
            <button
                type="button"
                class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                aria-label="New view"
                title="New view"
                @click="openNew"
            >
                <Plus class="size-3.5" />
            </button>
        </header>

        <div
            class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4"
        >
            <nav class="flex items-center gap-1 py-2 text-[12.5px]">
                <button
                    type="button"
                    :class="[
                        'rounded-md px-2 py-1 transition-colors',
                        !filters.scope
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                    ]"
                    @click="setScope(null)"
                >
                    All
                </button>
                <button
                    v-for="(label, key) in scopes"
                    :key="key"
                    type="button"
                    :class="[
                        'rounded-md px-2 py-1 transition-colors',
                        filters.scope === key
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                    ]"
                    @click="setScope(key)"
                >
                    {{ label }}
                </button>
            </nav>
            <div v-if="filters.team" class="text-[12px] text-muted-foreground">
                Team: <span class="text-foreground">{{ filters.team }}</span>
            </div>
        </div>

        <div
            v-if="!views.length"
            class="flex flex-1 items-center justify-center px-6 py-12 text-center"
        >
            <p class="text-sm text-muted-foreground">No views yet.</p>
        </div>

        <div v-else class="flex-1 overflow-y-auto px-4 py-3">
            <section v-for="g in grouped" :key="g.key" class="mb-6">
                <h3 class="mb-2 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                    {{ g.label }}
                    <span class="ml-1 text-muted-foreground tabular-nums">{{ g.items.length }}</span>
                </h3>
                <ul class="divide-y divide-border rounded-md border border-border">
                    <li
                        v-for="v in g.items"
                        :key="v.id"
                        class="group/row relative"
                    >
                        <Link
                            :href="`/views/${v.id}`"
                            class="flex items-center gap-3 px-3 py-2 hover:bg-accent/40"
                        >
                            <Layers class="size-4 shrink-0 text-muted-foreground" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="truncate text-[13px] text-foreground">{{ v.name }}</span>
                                    <span
                                        class="shrink-0 rounded-full bg-muted px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-muted-foreground"
                                    >
                                        {{ SCOPE_LABEL[v.scope ?? 'personal'] }}
                                    </span>
                                    <span
                                        v-if="v.team"
                                        class="shrink-0 inline-flex items-center gap-1 text-[11px] text-muted-foreground"
                                    >
                                        <span
                                            class="size-3 rounded-sm"
                                            :style="{ backgroundColor: v.team.color || '#6366f1' }"
                                        ></span>
                                        {{ v.team.key }}
                                    </span>
                                </div>
                                <p
                                    v-if="v.description"
                                    class="truncate text-[12px] text-muted-foreground"
                                >
                                    {{ v.description }}
                                </p>
                            </div>
                            <Avatar
                                v-if="v.owner"
                                :name="v.owner.name"
                                :email="v.owner.email"
                                :size="18"
                            />
                            <span class="shrink-0 text-[11px] text-muted-foreground tabular-nums">
                                {{ fmtShort(v.updated_at) }}
                            </span>
                        </Link>
                        <button
                            type="button"
                            class="absolute right-9 top-1/2 -translate-y-1/2 rounded p-1 transition-opacity"
                            :class="[
                                v.is_favorite
                                    ? 'text-amber-400 opacity-100 hover:bg-accent'
                                    : 'text-muted-foreground opacity-0 hover:bg-accent hover:text-foreground group-hover/row:opacity-100',
                            ]"
                            :aria-label="v.is_favorite ? 'Unfavourite' : 'Favourite'"
                            :title="v.is_favorite ? 'Unfavourite' : 'Favourite'"
                            @click="toggleFavorite(v, $event)"
                        >
                            <Star
                                class="size-3.5"
                                :fill="v.is_favorite ? 'currentColor' : 'none'"
                            />
                        </button>
                        <DropdownMenu v-if="v.is_owner">
                            <DropdownMenuTrigger as-child>
                                <button
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground opacity-0 transition-opacity hover:bg-accent hover:text-foreground group-hover/row:opacity-100"
                                    aria-label="View menu"
                                    @click.stop
                                >
                                    <MoreHorizontal class="size-3.5" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem class="text-rose-400" @select="deleteView(v)">
                                    Delete view
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </li>
                </ul>
            </section>
        </div>

        <!-- New view dialog -->
        <Dialog v-model:open="newDialogOpen">
            <DialogContent class="sm:max-w-[520px]">
                <DialogHeader>
                    <DialogTitle>New view</DialogTitle>
                    <DialogDescription>
                        Save the current filter set so you can jump back to it.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitNew">
                    <div class="space-y-1">
                        <label class="text-[12px] font-medium text-foreground" for="nv-name">Name</label>
                        <Input
                            id="nv-name"
                            v-model="newForm.name"
                            type="text"
                            placeholder="e.g. My in-progress bugs"
                            class="h-8 text-[13px]"
                            autofocus
                        />
                    </div>
                    <div class="space-y-1">
                        <label class="text-[12px] font-medium text-foreground" for="nv-desc">Description</label>
                        <textarea
                            id="nv-desc"
                            v-model="newForm.description"
                            rows="2"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-[13px] outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50"
                            placeholder="Optional description"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="text-[12px] font-medium text-foreground" for="nv-scope">Scope</label>
                            <select
                                id="nv-scope"
                                v-model="newForm.scope"
                                class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-[13px]"
                            >
                                <option value="personal">Personal</option>
                                <option value="team">Team</option>
                                <option value="workspace">Workspace</option>
                            </select>
                        </div>
                        <div v-if="newForm.scope === 'team'" class="space-y-1">
                            <label class="text-[12px] font-medium text-foreground" for="nv-team">Team</label>
                            <select
                                id="nv-team"
                                v-model="newForm.team_key"
                                class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-[13px]"
                            >
                                <option value="">Select a team…</option>
                                <option v-for="t in teams" :key="t.id" :value="t.key">
                                    {{ t.name }} ({{ t.key }})
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="rounded-md border border-border bg-muted/30 px-3 py-2">
                        <h4 class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                            Saved filters
                        </h4>
                        <p
                            v-if="!Object.keys(newForm.filters).length"
                            class="mt-1 text-[12px] text-muted-foreground"
                        >
                            No filters set. The view will list all issues.
                        </p>
                        <ul v-else class="mt-1 space-y-1 text-[12px] text-foreground">
                            <li
                                v-for="(value, key) in newForm.filters"
                                :key="String(key)"
                                class="flex items-center justify-between"
                            >
                                <span class="text-muted-foreground">{{ key }}</span>
                                <span class="font-mono text-foreground">{{ Array.isArray(value) ? value.join(',') : String(value) }}</span>
                            </li>
                        </ul>
                    </div>
                    <p v-if="newError" class="text-[12px] text-rose-400">{{ newError }}</p>
                    <DialogFooter>
                        <DialogClose
                            class="rounded-md px-3 py-1.5 text-[13px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        >
                            Cancel
                        </DialogClose>
                        <button
                            type="submit"
                            :disabled="newSubmitting"
                            class="rounded-md bg-foreground px-3 py-1.5 text-[13px] font-medium text-background transition-opacity hover:opacity-90 disabled:opacity-50"
                        >
                            {{ newSubmitting ? 'Saving…' : 'Save view' }}
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
