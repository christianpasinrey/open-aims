<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { LayoutGrid } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const props = defineProps<{
    filters: {
        team: string | null;
        assignee: string | null;
        state: string | null;
        priority: number | null;
        project: number | null;
        labels: number[];
        group: string;
        sort: string;
    };
}>();

const groupValue = computed(() => props.filters.group);
const sortValue = computed(() => props.filters.sort);

function navigate(patch: Record<string, string | number | null>): void {
    const params: Record<string, string> = {};
    if (props.filters.team) params.team = props.filters.team;
    if (props.filters.assignee) params.assignee = props.filters.assignee;
    if (props.filters.state) params.state = props.filters.state;
    if (props.filters.priority !== null && props.filters.priority !== undefined) {
        params.priority = String(props.filters.priority);
    }
    if (props.filters.project !== null && props.filters.project !== undefined) {
        params.project = String(props.filters.project);
    }
    if (props.filters.labels.length) {
        params.labels = props.filters.labels.join(',');
    }
    if (props.filters.group && props.filters.group !== 'status') {
        params.group = props.filters.group;
    }
    if (props.filters.sort && props.filters.sort !== 'priority') {
        params.sort = props.filters.sort;
    }

    for (const [k, v] of Object.entries(patch)) {
        if (v === null || v === '' || v === undefined) {
            delete params[k];
        } else {
            params[k] = String(v);
        }
    }

    router.get('/issues', params, {
        preserveState: false,
        preserveScroll: true,
    });
}

function setGroup(value: string): void {
    navigate({ group: value === 'status' ? null : value });
}
function setSort(value: string): void {
    navigate({ sort: value === 'priority' ? null : value });
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                aria-label="Display options"
                title="Display options"
            >
                <LayoutGrid class="size-3.5" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56">
            <DropdownMenuLabel>Group by</DropdownMenuLabel>
            <DropdownMenuRadioGroup
                :model-value="groupValue"
                @update:model-value="(v) => setGroup(String(v))"
            >
                <DropdownMenuRadioItem value="status">Status</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="priority">Priority</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="assignee">Assignee</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="project">Project</DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
            <DropdownMenuSeparator />
            <DropdownMenuLabel>Ordering</DropdownMenuLabel>
            <DropdownMenuRadioGroup
                :model-value="sortValue"
                @update:model-value="(v) => setSort(String(v))"
            >
                <DropdownMenuRadioItem value="priority">Priority</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="updated">Last updated</DropdownMenuRadioItem>
                <DropdownMenuRadioItem value="created">Created</DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
