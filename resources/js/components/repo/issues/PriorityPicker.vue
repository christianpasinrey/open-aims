<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import PriorityIcon from '@/components/repo/PriorityIcon.vue';
import PropertyRow from './PropertyRow.vue';

const props = defineProps<{
    identifier: string;
    current: number;
    currentLabel: string;
    priorities: Record<string, string>;
}>();

const order = [1, 2, 3, 4, 0]; // Urgent, High, Medium, Low, No priority

function pick(p: number): void {
    if (props.current === p) return;
    router.patch(
        `/issues/${props.identifier}`,
        { priority: p },
        { preserveScroll: true },
    );
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <PropertyRow :empty="current === 0" label="Priority">
                <PriorityIcon :priority="current" :size="14" />
                <span>{{ currentLabel }}</span>
            </PropertyRow>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="w-52">
            <DropdownMenuItem
                v-for="p in order"
                :key="p"
                @select="pick(p)"
            >
                <PriorityIcon :priority="p" :size="14" />
                <span class="flex-1">{{ priorities[String(p)] ?? '' }}</span>
                <Check v-if="current === p" class="size-3.5 text-foreground" />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
