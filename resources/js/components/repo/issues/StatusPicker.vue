<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import StatusIcon from '@/components/repo/StatusIcon.vue';
import PropertyRow from './PropertyRow.vue';
import { startedProgressByState } from '@/lib/states';

type State = {
    id: number;
    name: string;
    type: string;
    color: string;
    position: number;
};

const props = defineProps<{
    identifier: string;
    states: State[];
    current: { id: number; name: string; type: string; color: string } | null;
}>();

const startedProgress = computed(() => startedProgressByState(props.states));

const TYPE_RANK: Record<string, number> = {
    triage: 0,
    started: 1,
    unstarted: 2,
    backlog: 3,
    completed: 4,
    canceled: 5,
};
const ordered = computed(() =>
    [...props.states].sort((a, b) => {
        const ta = TYPE_RANK[a.type] ?? 99;
        const tb = TYPE_RANK[b.type] ?? 99;
        if (ta !== tb) return ta - tb;
        return a.position - b.position;
    }),
);

function pick(stateId: number): void {
    if (props.current?.id === stateId) return;
    router.patch(
        `/issues/${props.identifier}`,
        { workflow_state_id: stateId },
        { preserveScroll: true },
    );
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <PropertyRow :empty="!current" label="Status">
                <template v-if="current">
                    <StatusIcon
                        :type="current.type"
                        :color="current.color"
                        :progress="startedProgress[current.id]"
                    />
                    <span>{{ current.name }}</span>
                </template>
                <template v-else>
                    <span class="size-3.5 rounded-full border border-dashed border-border"></span>
                    <span>—</span>
                </template>
            </PropertyRow>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="max-h-72 w-56 overflow-y-auto">
            <DropdownMenuItem
                v-for="s in ordered"
                :key="s.id"
                @select="pick(s.id)"
            >
                <StatusIcon
                    :type="s.type"
                    :color="s.color"
                    :progress="startedProgress[s.id]"
                    :size="14"
                />
                <span class="flex-1 truncate">{{ s.name }}</span>
                <Check
                    v-if="current?.id === s.id"
                    class="size-3.5 text-foreground"
                />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
