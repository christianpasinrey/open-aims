<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';
import { Calendar, Plus, SlidersHorizontal } from 'lucide-vue-next';
import Avatar from '@/components/repo/Avatar.vue';
import InitiativeIcon from '@/components/repo/initiatives/InitiativeIcon.vue';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

type Member = { id: number; name: string; email: string };
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
    parent_initiative_id: number | null;
    projects_count: number;
    total_issues: number;
    completed_issues: number;
    completion_percent: number;
};
type Filters = { state: string | null; owner: number | null };

const props = defineProps<{
    initiatives: Initiative[];
    states: Record<string, string>;
    members: Member[];
    filters: Filters;
}>();

const STATE_LABELS: Record<string, string> = {
    planned: 'Planned',
    active: 'Active',
    completed: 'Completed',
    canceled: 'Canceled',
};
const STATE_ORDER = ['active', 'planned', 'completed', 'canceled'];

function fmtDate(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

function isOverdue(target: string | null, completedAt: string | null) {
    if (!target || completedAt) return false;
    return new Date(target).getTime() < Date.now();
}

const ringR = 5;
const ringC = 2 * Math.PI * ringR;
function ringDashOffset(percent: number) {
    return ringC * (1 - Math.max(0, Math.min(100, percent)) / 100);
}
function ringStroke(percent: number, state: string | null) {
    if (state === 'canceled') return '#a1a1aa';
    if (state === 'completed' || percent >= 100) return '#10b981';
    if (percent > 0) return '#f59e0b';
    return '#a1a1aa';
}

// ---- URL filter helpers ----
type ParamPatch = {
    state?: string | null;
    owner?: number | null;
};
function applyParams(patch: ParamPatch) {
    const current: Record<string, string | null | number | undefined> = {
        state: props.filters.state ?? null,
        owner: props.filters.owner ?? null,
        ...patch,
    };
    const merged: Record<string, string> = {};
    for (const [k, v] of Object.entries(current)) {
        if (v === null || v === undefined || v === '') continue;
        merged[k] = String(v);
    }
    router.get('/initiatives', merged, { preserveState: false, replace: false });
}
function setState(value: string | null) {
    applyParams({ state: value });
}
function setOwner(value: number | null) {
    applyParams({ owner: value });
}
function clearFilters() {
    applyParams({ state: null, owner: null });
}
const activeFilterCount = computed<number>(() => {
    let c = 0;
    if (props.filters.state) c++;
    if (props.filters.owner) c++;
    return c;
});

// ---- New initiative dialog ----
const newDialogOpen = ref<boolean>(false);
const newForm = ref<{
    name: string;
    description: string;
    state: string;
    owner_user_id: number | null;
    color: string;
    icon: string;
    start_date: string;
    target_date: string;
}>({
    name: '',
    description: '',
    state: 'planned',
    owner_user_id: null,
    color: '#6366f1',
    icon: '',
    start_date: '',
    target_date: '',
});
const newSubmitting = ref<boolean>(false);
const newError = ref<string | null>(null);

function openNew() {
    newForm.value = {
        name: '',
        description: '',
        state: 'planned',
        owner_user_id: null,
        color: '#6366f1',
        icon: '',
        start_date: '',
        target_date: '',
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
        state: newForm.value.state,
        color: newForm.value.color,
    };
    if (newForm.value.description.trim() !== '') {
        payload.description = newForm.value.description.trim();
    }
    if (newForm.value.icon.trim() !== '') {
        payload.icon = newForm.value.icon.trim();
    }
    if (newForm.value.owner_user_id !== null) {
        payload.owner_user_id = newForm.value.owner_user_id;
    }
    if (newForm.value.start_date !== '') {
        payload.start_date = newForm.value.start_date;
    }
    if (newForm.value.target_date !== '') {
        payload.target_date = newForm.value.target_date;
    }
    router.post('/initiatives', payload, {
        onSuccess: () => {
            newDialogOpen.value = false;
            newSubmitting.value = false;
        },
        onError: (errors) => {
            newSubmitting.value = false;
            const first = Object.values(errors)[0];
            newError.value = (first as string | undefined) ?? 'Could not create initiative.';
        },
        onFinish: () => {
            newSubmitting.value = false;
        },
    });
}

const colorPalette = [
    '#6366f1',
    '#8b5cf6',
    '#ec4899',
    '#ef4444',
    '#f59e0b',
    '#10b981',
    '#06b6d4',
    '#3b82f6',
];
</script>

<template>
    <Head title="Initiatives" />

    <div class="flex h-full flex-1 flex-col overflow-hidden">
        <header
            class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4 py-2.5"
        >
            <div class="flex min-w-0 items-center gap-2">
                <h1 class="text-[13px] font-medium">Initiatives</h1>
            </div>
            <button
                type="button"
                class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                aria-label="New initiative"
                title="New initiative"
                @click="openNew"
            >
                <Plus class="size-3.5" />
            </button>
        </header>

        <div
            class="flex shrink-0 items-center justify-end gap-3 border-b border-border px-4"
        >
            <div class="flex items-center gap-1 py-2 text-muted-foreground">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            :class="[
                                'flex items-center gap-1 rounded-md p-1.5 transition-colors hover:bg-accent hover:text-foreground',
                                activeFilterCount > 0 && 'text-foreground',
                            ]"
                            aria-label="Filter"
                            title="Filter"
                        >
                            <SlidersHorizontal class="size-3.5" />
                            <span
                                v-if="activeFilterCount > 0"
                                class="text-[11px] tabular-nums"
                                >{{ activeFilterCount }}</span
                            >
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <DropdownMenuLabel>Filter by</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuSub>
                            <DropdownMenuSubTrigger>State</DropdownMenuSubTrigger>
                            <DropdownMenuSubContent class="w-48">
                                <DropdownMenuCheckboxItem
                                    v-for="key in STATE_ORDER"
                                    :key="key"
                                    :model-value="props.filters.state === key"
                                    @select="(e) => { e.preventDefault(); setState(props.filters.state === key ? null : key); }"
                                >
                                    {{ STATE_LABELS[key] }}
                                </DropdownMenuCheckboxItem>
                            </DropdownMenuSubContent>
                        </DropdownMenuSub>
                        <DropdownMenuSub>
                            <DropdownMenuSubTrigger>Owner</DropdownMenuSubTrigger>
                            <DropdownMenuSubContent class="max-h-72 w-56 overflow-y-auto">
                                <DropdownMenuCheckboxItem
                                    v-for="m in members"
                                    :key="m.id"
                                    :model-value="props.filters.owner === m.id"
                                    @select="(e) => { e.preventDefault(); setOwner(props.filters.owner === m.id ? null : m.id); }"
                                >
                                    <span class="flex min-w-0 items-center gap-2">
                                        <Avatar :name="m.name" :email="m.email" :size="14" />
                                        <span class="truncate">{{ m.name }}</span>
                                    </span>
                                </DropdownMenuCheckboxItem>
                                <DropdownMenuItem v-if="!members.length" disabled>
                                    No members
                                </DropdownMenuItem>
                            </DropdownMenuSubContent>
                        </DropdownMenuSub>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            :disabled="activeFilterCount === 0"
                            @select="clearFilters"
                        >
                            Clear filters
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <div
            v-if="!initiatives.length"
            class="flex flex-1 items-center justify-center px-6 py-12 text-center"
        >
            <p class="text-sm text-muted-foreground">No initiatives yet.</p>
        </div>

        <div v-else class="flex-1 overflow-y-auto">
            <div
                class="sticky top-0 z-10 grid grid-cols-[1fr_180px_110px_70px_80px] items-center gap-4 border-b border-border bg-background px-4 py-2 text-[11px] font-medium uppercase tracking-wide text-muted-foreground"
            >
                <span>Name</span>
                <span>Owner</span>
                <span>Target date</span>
                <span class="text-right">Projects</span>
                <span class="text-right">Progress</span>
            </div>

            <ul class="divide-y divide-border">
                <li
                    v-for="initiative in initiatives"
                    :key="initiative.id"
                    class="group/row relative"
                >
                    <Link
                        :href="`/initiatives/${initiative.slug}`"
                        class="grid grid-cols-[1fr_180px_110px_70px_80px] items-center gap-4 px-4 py-2 hover:bg-accent/40"
                    >
                        <div class="flex min-w-0 items-center gap-2.5">
                            <InitiativeIcon
                                :icon="initiative.icon"
                                :color="initiative.color"
                                :size="18"
                            />
                            <span class="truncate text-[13px] text-foreground">{{ initiative.name }}</span>
                            <span
                                v-if="initiative.state"
                                class="shrink-0 rounded-full bg-muted px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-muted-foreground"
                            >
                                {{ STATE_LABELS[initiative.state] ?? initiative.state }}
                            </span>
                        </div>

                        <div v-if="initiative.owner" class="flex min-w-0 items-center gap-2 text-[12.5px]">
                            <Avatar :name="initiative.owner.name" :email="initiative.owner.email" :size="18" />
                            <span class="truncate text-foreground">{{ initiative.owner.name }}</span>
                        </div>
                        <div v-else class="flex items-center gap-2 text-[12px] text-muted-foreground">
                            <span class="flex size-[18px] items-center justify-center rounded-full border border-dashed border-border"></span>
                            <span>No owner</span>
                        </div>

                        <span
                            v-if="initiative.target_date"
                            class="inline-flex items-center gap-1 text-[12px]"
                            :class="isOverdue(initiative.target_date, initiative.completed_at) ? 'text-rose-400' : 'text-muted-foreground'"
                        >
                            <Calendar class="size-3.5" />
                            {{ fmtDate(initiative.target_date) }}
                        </span>
                        <span v-else class="text-[12px] text-muted-foreground">&mdash;</span>

                        <span class="text-right text-[12.5px] text-muted-foreground tabular-nums">
                            {{ initiative.projects_count }}
                        </span>

                        <div class="flex items-center justify-end gap-1.5">
                            <svg
                                width="14"
                                height="14"
                                viewBox="0 0 14 14"
                                fill="none"
                                class="shrink-0"
                                aria-hidden="true"
                            >
                                <circle cx="7" cy="7" r="5" stroke="#3f3f46" stroke-width="1.5" fill="none" />
                                <circle
                                    cx="7"
                                    cy="7"
                                    r="5"
                                    fill="none"
                                    stroke-width="2"
                                    :stroke="ringStroke(initiative.completion_percent, initiative.state)"
                                    :stroke-dasharray="`${ringC} ${ringC}`"
                                    :stroke-dashoffset="ringDashOffset(initiative.completion_percent)"
                                    transform="rotate(-90 7 7)"
                                    stroke-linecap="butt"
                                />
                            </svg>
                            <span class="text-[12px] text-foreground tabular-nums">{{ initiative.completion_percent }}%</span>
                        </div>
                    </Link>
                </li>
            </ul>
        </div>

        <!-- New initiative dialog -->
        <Dialog v-model:open="newDialogOpen">
            <DialogContent class="sm:max-w-[520px]">
                <DialogHeader>
                    <DialogTitle>New initiative</DialogTitle>
                    <DialogDescription>
                        Group projects under a workspace-level goal.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitNew">
                    <div class="space-y-1">
                        <label class="text-[12px] font-medium text-foreground" for="ni-name">Name</label>
                        <Input
                            id="ni-name"
                            v-model="newForm.name"
                            type="text"
                            placeholder="e.g. Mobile launch"
                            class="h-8 text-[13px]"
                            autofocus
                        />
                    </div>
                    <div class="space-y-1">
                        <label class="text-[12px] font-medium text-foreground" for="ni-desc">Description</label>
                        <textarea
                            id="ni-desc"
                            v-model="newForm.description"
                            rows="3"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-[13px] outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50"
                            placeholder="What's the goal? How will we know it's done?"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="text-[12px] font-medium text-foreground" for="ni-state">State</label>
                            <select
                                id="ni-state"
                                v-model="newForm.state"
                                class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-[13px] capitalize"
                            >
                                <option v-for="key in STATE_ORDER" :key="key" :value="key">
                                    {{ STATE_LABELS[key] }}
                                </option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[12px] font-medium text-foreground" for="ni-owner">Owner</label>
                            <select
                                id="ni-owner"
                                v-model="newForm.owner_user_id"
                                class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-[13px]"
                            >
                                <option :value="null">No owner</option>
                                <option
                                    v-for="m in members"
                                    :key="m.id"
                                    :value="m.id"
                                >
                                    {{ m.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <span class="text-[12px] font-medium text-foreground">Color</span>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="c in colorPalette"
                                :key="c"
                                type="button"
                                :class="[
                                    'size-6 rounded-md border-2 transition-all',
                                    newForm.color === c
                                        ? 'border-foreground'
                                        : 'border-transparent',
                                ]"
                                :style="{ backgroundColor: c }"
                                @click="newForm.color = c"
                                :aria-label="c"
                            />
                        </div>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[12px] font-medium text-foreground" for="ni-icon">Icon (emoji)</label>
                        <Input
                            id="ni-icon"
                            v-model="newForm.icon"
                            type="text"
                            placeholder="e.g. rocket, target, fire"
                            class="h-8 text-[13px]"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="text-[12px] font-medium text-foreground" for="ni-start">Start date</label>
                            <input
                                id="ni-start"
                                v-model="newForm.start_date"
                                type="date"
                                class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-[13px]"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-[12px] font-medium text-foreground" for="ni-target">Target date</label>
                            <input
                                id="ni-target"
                                v-model="newForm.target_date"
                                type="date"
                                class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-[13px]"
                            />
                        </div>
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
                            {{ newSubmitting ? 'Creating…' : 'Create initiative' }}
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
