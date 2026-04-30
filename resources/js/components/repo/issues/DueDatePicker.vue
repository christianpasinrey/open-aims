<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Calendar, Plus, X } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const props = defineProps<{
    identifier: string;
    current: string | null;
    /** Used to apply red tint for past dates on incomplete issues. */
    overdue?: boolean;
}>();

const localValue = ref<string>(props.current ?? '');
const open = ref(false);

watch(
    () => props.current,
    (v) => {
        localValue.value = v ?? '';
    },
);

const display = computed(() => {
    if (!props.current) return null;
    const target = new Date(props.current);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    target.setHours(0, 0, 0, 0);
    const diffDays = Math.round((target.getTime() - today.getTime()) / 86_400_000);
    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Tomorrow';
    if (diffDays === -1) return 'Yesterday';
    if (diffDays > 1 && diffDays < 7) {
        return target.toLocaleDateString(undefined, { weekday: 'long' });
    }
    return target.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        ...(target.getFullYear() === today.getFullYear() ? {} : { year: 'numeric' }),
    });
});

function commit(value: string | null): void {
    router.patch(
        `/issues/${props.identifier}`,
        { due_date: value },
        { preserveScroll: true },
    );
    open.value = false;
}

function onDateInput(e: Event): void {
    const next = (e.target as HTMLInputElement).value;
    if (!next) return;
    commit(next);
}
</script>

<template>
    <DropdownMenu v-model:open="open">
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="flex w-full items-center gap-2 rounded px-1 py-1 text-left text-[13px]"
                :class="
                    overdue
                        ? 'text-red-400 hover:bg-red-500/10'
                        : current
                            ? 'text-foreground hover:bg-accent/60'
                            : 'text-muted-foreground hover:bg-accent/60'
                "
                aria-label="Due date"
            >
                <Calendar
                    class="size-3.5 shrink-0"
                    :class="overdue ? 'text-red-400' : 'text-muted-foreground'"
                />
                <span v-if="current">{{ display }}</span>
                <span v-else class="inline-flex items-center gap-1">
                    <Plus class="size-3" />
                    <span>Set due date</span>
                </span>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="w-56 p-2">
            <label class="mb-1 block text-[11px] uppercase tracking-wide text-muted-foreground">
                Due date
            </label>
            <input
                v-model="localValue"
                type="date"
                class="w-full rounded border border-border bg-background px-2 py-1 text-[13px] text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                @change="onDateInput"
            />
            <DropdownMenuSeparator class="my-2" />
            <DropdownMenuItem
                :disabled="!current"
                @select="commit(null)"
            >
                <X class="size-3.5" />
                <span>Clear</span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
