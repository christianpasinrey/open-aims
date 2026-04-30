<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import PropertyRow from './PropertyRow.vue';

const props = defineProps<{
    identifier: string;
    current: number | null;
}>();

// Modified Fibonacci — same set repo ships by default.
const points = [0, 1, 2, 3, 5, 8, 13];

function pick(value: number | null): void {
    if ((props.current ?? null) === value) {
        return;
    }

    router.patch(
        `/issues/${props.identifier}`,
        { estimate: value },
        { preserveScroll: true },
    );
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <PropertyRow
                :empty="current === null || current === undefined"
                label="Estimate"
            >
                <span
                    class="flex size-3.5 items-center justify-center rounded-full border"
                    :class="
                        current === null || current === undefined
                            ? 'border-dashed border-border'
                            : 'border-border bg-card text-[10px] text-foreground'
                    "
                >
                    <span
                        v-if="current !== null && current !== undefined"
                        class="leading-none"
                        >{{ current }}</span
                    >
                </span>
                <span v-if="current === null || current === undefined"
                    >No estimate</span
                >
                <span v-else>{{ current }} points</span>
            </PropertyRow>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="w-44">
            <DropdownMenuItem @select="pick(null)">
                <span
                    class="size-3.5 rounded-full border border-dashed border-border"
                ></span>
                <span class="flex-1">No estimate</span>
                <Check
                    v-if="current === null || current === undefined"
                    class="size-3.5 text-foreground"
                />
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem v-for="p in points" :key="p" @select="pick(p)">
                <span
                    class="flex size-4 items-center justify-center rounded-full border border-border bg-card text-[10px]"
                    >{{ p }}</span
                >
                <span class="flex-1"
                    >{{ p }} {{ p === 1 ? 'point' : 'points' }}</span
                >
                <Check v-if="current === p" class="size-3.5 text-foreground" />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
