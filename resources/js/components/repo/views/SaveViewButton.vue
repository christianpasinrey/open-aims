<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';
import { Bookmark } from 'lucide-vue-next';
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

type Filters = {
    team: string | null;
    assignee: string | null;
    state: string | null;
    priority: number | null;
    project: number | null;
    labels: number[];
    group: string;
    sort: string;
};
type WorkspaceTeam = { id: number; name: string; key: string; color: string | null };

const props = defineProps<{
    filters: Filters;
    teams: WorkspaceTeam[];
}>();

const isDefault = computed<boolean>(() => {
    const f = props.filters;
    return (
        !f.assignee &&
        !f.state &&
        f.priority === null &&
        f.project === null &&
        (f.labels?.length ?? 0) === 0 &&
        (f.group === 'status' || !f.group) &&
        (f.sort === 'priority' || !f.sort)
    );
});

const open = ref<boolean>(false);
const submitting = ref<boolean>(false);
const error = ref<string | null>(null);

const form = ref<{
    name: string;
    description: string;
    scope: 'personal' | 'team' | 'workspace';
    team_key: string;
}>({
    name: '',
    description: '',
    scope: props.filters.team ? 'team' : 'personal',
    team_key: props.filters.team ?? '',
});

function openDialog() {
    form.value = {
        name: '',
        description: '',
        scope: props.filters.team ? 'team' : 'personal',
        team_key: props.filters.team ?? '',
    };
    error.value = null;
    open.value = true;
}

function submit() {
    if (!form.value.name.trim()) {
        error.value = 'Name is required.';
        return;
    }
    submitting.value = true;
    error.value = null;
    const payload: Record<string, FormDataConvertible> = {
        name: form.value.name.trim(),
        scope: form.value.scope,
        grouping: props.filters.group ?? 'status',
        sorting: props.filters.sort ?? 'priority',
    };
    if (form.value.description.trim() !== '') {
        payload.description = form.value.description.trim();
    }
    if (form.value.scope === 'team' && form.value.team_key !== '') {
        payload.team_key = form.value.team_key;
    }
    if (props.filters.team) payload['filters[team]'] = props.filters.team;
    if (props.filters.assignee) payload['filters[assignee]'] = props.filters.assignee;
    if (props.filters.state) payload['filters[state]'] = props.filters.state;
    if (props.filters.priority !== null) payload['filters[priority]'] = props.filters.priority;
    if (props.filters.project !== null) payload['filters[project]'] = props.filters.project;
    if (props.filters.labels && props.filters.labels.length > 0) {
        for (let i = 0; i < props.filters.labels.length; i++) {
            payload[`filters[labels][${i}]`] = props.filters.labels[i] as number;
        }
    }
    router.post('/views', payload, {
        onSuccess: () => {
            open.value = false;
            submitting.value = false;
        },
        onError: (errors) => {
            submitting.value = false;
            const first = Object.values(errors)[0];
            error.value = (first as string | undefined) ?? 'Could not save view.';
        },
        onFinish: () => {
            submitting.value = false;
        },
    });
}
</script>

<template>
    <button
        v-if="!isDefault"
        type="button"
        class="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-[12px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
        title="Save current filters as a view"
        @click="openDialog"
    >
        <Bookmark class="size-3.5" />
        Save view
    </button>

    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-[480px]">
            <DialogHeader>
                <DialogTitle>Save view</DialogTitle>
                <DialogDescription>
                    Save the current filters and grouping as a reusable view.
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-1">
                    <label class="text-[12px] font-medium text-foreground" for="sv-name">Name</label>
                    <Input
                        id="sv-name"
                        v-model="form.name"
                        type="text"
                        placeholder="e.g. My in-progress bugs"
                        class="h-8 text-[13px]"
                        autofocus
                    />
                </div>
                <div class="space-y-1">
                    <label class="text-[12px] font-medium text-foreground" for="sv-desc">Description</label>
                    <textarea
                        id="sv-desc"
                        v-model="form.description"
                        rows="2"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-[13px] outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50"
                        placeholder="Optional"
                    />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="text-[12px] font-medium text-foreground" for="sv-scope">Scope</label>
                        <select
                            id="sv-scope"
                            v-model="form.scope"
                            class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-[13px]"
                        >
                            <option value="personal">Personal</option>
                            <option value="team">Team</option>
                            <option value="workspace">Workspace</option>
                        </select>
                    </div>
                    <div v-if="form.scope === 'team'" class="space-y-1">
                        <label class="text-[12px] font-medium text-foreground" for="sv-team">Team</label>
                        <select
                            id="sv-team"
                            v-model="form.team_key"
                            class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-[13px]"
                        >
                            <option value="">Select team</option>
                            <option v-for="t in teams" :key="t.id" :value="t.key">
                                {{ t.name }} ({{ t.key }})
                            </option>
                        </select>
                    </div>
                </div>
                <div class="rounded-md border border-border bg-muted/30 px-3 py-2 text-[12px]">
                    <h4 class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                        Current filters
                    </h4>
                    <ul class="mt-1 space-y-0.5 font-mono">
                        <li v-if="filters.team"><span class="text-muted-foreground">team:</span> {{ filters.team }}</li>
                        <li v-if="filters.assignee"><span class="text-muted-foreground">assignee:</span> {{ filters.assignee }}</li>
                        <li v-if="filters.state"><span class="text-muted-foreground">state:</span> {{ filters.state }}</li>
                        <li v-if="filters.priority !== null"><span class="text-muted-foreground">priority:</span> {{ filters.priority }}</li>
                        <li v-if="filters.project !== null"><span class="text-muted-foreground">project:</span> {{ filters.project }}</li>
                        <li v-if="filters.labels.length"><span class="text-muted-foreground">labels:</span> {{ filters.labels.join(',') }}</li>
                        <li><span class="text-muted-foreground">group:</span> {{ filters.group }}</li>
                        <li><span class="text-muted-foreground">sort:</span> {{ filters.sort }}</li>
                    </ul>
                </div>
                <p v-if="error" class="text-[12px] text-rose-400">{{ error }}</p>
                <DialogFooter>
                    <DialogClose
                        class="rounded-md px-3 py-1.5 text-[13px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    >
                        Cancel
                    </DialogClose>
                    <button
                        type="submit"
                        :disabled="submitting"
                        class="rounded-md bg-foreground px-3 py-1.5 text-[13px] font-medium text-background transition-opacity hover:opacity-90 disabled:opacity-50"
                    >
                        {{ submitting ? 'Saving…' : 'Save view' }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
