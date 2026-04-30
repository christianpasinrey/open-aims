<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import LabelBadge from '@/components/repo/LabelBadge.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type Label = { id: number; name: string; color?: string | null };

const props = defineProps<{
    identifier: string;
    labels: Label[];
    current: Label[];
}>();

const open = ref(false);
const query = ref('');
const localSelection = ref<Set<number>>(
    new Set(props.current.map((l) => l.id)),
);

watch(
    () => props.current,
    (next) => {
        localSelection.value = new Set(next.map((l) => l.id));
    },
    { deep: true },
);

watch(open, (isOpen) => {
    if (!isOpen) {
        query.value = '';
    }
});

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();

    if (!q) {
        return props.labels;
    }

    return props.labels.filter((l) => l.name.toLowerCase().includes(q));
});

function toggle(id: number): void {
    const next = new Set(localSelection.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    localSelection.value = next;
    router.patch(
        `/issues/${props.identifier}`,
        { labels: [...next] },
        { preserveScroll: true },
    );
}
</script>

<template>
    <div>
        <DropdownMenu v-model:open="open">
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    class="flex w-full items-center gap-1.5 rounded text-left"
                    aria-label="Labels"
                >
                    <template v-if="current.length">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <LabelBadge
                                v-for="l in current"
                                :key="l.id"
                                :name="l.name"
                                :color="l.color"
                            />
                            <span
                                class="inline-flex size-[18px] items-center justify-center rounded-full border border-dashed border-border text-muted-foreground transition-colors hover:border-foreground hover:text-foreground"
                                aria-label="Add label"
                            >
                                <Plus class="size-3" />
                            </span>
                        </div>
                    </template>
                    <span
                        v-else
                        class="inline-flex items-center gap-1.5 rounded-full border border-dashed border-border px-2 py-px text-[11px] leading-[16px] text-muted-foreground transition-colors hover:border-foreground hover:text-foreground"
                    >
                        <Plus class="size-3" />
                        <span>Add label</span>
                    </span>
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-56 p-0">
                <div class="border-b border-border p-1.5">
                    <input
                        v-model="query"
                        type="text"
                        placeholder="Search labels…"
                        class="w-full rounded bg-transparent px-1.5 py-1 text-[13px] text-foreground placeholder:text-muted-foreground focus:outline-none"
                        @keydown.stop
                    />
                </div>
                <div class="max-h-72 overflow-y-auto p-1">
                    <DropdownMenuItem
                        v-for="l in filtered"
                        :key="l.id"
                        @select.prevent="toggle(l.id)"
                    >
                        <span
                            class="size-2.5 shrink-0 rounded-full"
                            :style="{ backgroundColor: l.color ?? '#94a3b8' }"
                        ></span>
                        <span class="flex-1 truncate">{{ l.name }}</span>
                        <Check
                            v-if="localSelection.has(l.id)"
                            class="size-3.5 text-foreground"
                        />
                    </DropdownMenuItem>
                    <div
                        v-if="filtered.length === 0"
                        class="px-2 py-1.5 text-xs text-muted-foreground"
                    >
                        No labels
                    </div>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
