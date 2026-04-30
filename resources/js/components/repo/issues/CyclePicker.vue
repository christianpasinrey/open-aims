<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Play } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import PropertyRow from './PropertyRow.vue';

type Cycle = {
    id: number;
    number: number;
    name: string | null;
    starts_at: string | null;
    ends_at: string | null;
};

const props = defineProps<{
    identifier: string;
    cycles: Cycle[];
    current: Cycle | null;
}>();

function pick(id: number | null): void {
    if ((props.current?.id ?? null) === id) {
        return;
    }

    router.patch(
        `/issues/${props.identifier}`,
        { cycle_id: id },
        { preserveScroll: true },
    );
}

function cycleTitle(c: Cycle): string {
    return c.name ?? `Cycle ${c.number}`;
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <PropertyRow :empty="!current" label="Cycle">
                <template v-if="current">
                    <Play
                        class="size-3.5 shrink-0 fill-indigo-400 text-indigo-400"
                    />
                    <span>{{ cycleTitle(current) }}</span>
                </template>
                <template v-else>
                    <span
                        class="size-3.5 rounded-full border border-dashed border-border"
                    ></span>
                    <span>No cycle</span>
                </template>
            </PropertyRow>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="start"
            class="max-h-72 w-60 overflow-y-auto"
        >
            <DropdownMenuItem @select="pick(null)">
                <span
                    class="size-3.5 rounded-full border border-dashed border-border"
                ></span>
                <span class="flex-1">No cycle</span>
                <Check v-if="!current" class="size-3.5 text-foreground" />
            </DropdownMenuItem>
            <DropdownMenuSeparator v-if="cycles.length" />
            <DropdownMenuItem
                v-for="c in cycles"
                :key="c.id"
                @select="pick(c.id)"
            >
                <Play
                    class="size-3.5 shrink-0 fill-indigo-400 text-indigo-400"
                />
                <span class="flex-1 truncate">{{ cycleTitle(c) }}</span>
                <Check
                    v-if="current?.id === c.id"
                    class="size-3.5 text-foreground"
                />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
