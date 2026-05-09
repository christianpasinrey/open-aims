<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Loader2, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
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
const creating = ref(false);
const localSelection = ref<Set<number>>(
    new Set((props.current ?? []).map((l) => l.id)),
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

    return (props.labels ?? []).filter((l) => l.name.toLowerCase().includes(q));
});

const exactMatch = computed<boolean>(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) {
        return false;
    }
    return (props.labels ?? []).some((l) => l.name.toLowerCase() === q);
});

const teamKey = computed<string>(() =>
    (props.identifier.split('-')[0] ?? '').toUpperCase(),
);

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

async function quickCreate(): Promise<void> {
    const name = query.value.trim();
    if (!name || !teamKey.value || creating.value) {
        return;
    }

    creating.value = true;
    try {
        const res = await fetch(`/teams/${teamKey.value}/labels`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    (
                        document.querySelector(
                            'meta[name="csrf-token"]',
                        ) as HTMLMetaElement | null
                    )?.content ?? '',
            },
            body: JSON.stringify({ name }),
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            toast.error(
                (err?.message as string | undefined) ??
                    'Could not create label',
            );
            return;
        }
        const created = (await res.json()) as { id: number; name: string };
        query.value = '';
        toggle(created.id);
    } catch {
        toast.error('Could not create label');
    } finally {
        creating.value = false;
    }
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
                    <button
                        v-if="query.trim() && !exactMatch && teamKey"
                        type="button"
                        :disabled="creating"
                        class="mt-1 flex w-full items-center gap-2 border-t border-border px-2 py-1.5 text-left text-[12.5px] text-foreground hover:bg-accent disabled:opacity-50"
                        @click="quickCreate"
                    >
                        <Loader2
                            v-if="creating"
                            class="size-3.5 animate-spin"
                        />
                        <Plus v-else class="size-3.5" />
                        <span>
                            Create label
                            <span class="font-medium"
                                >“{{ query.trim() }}”</span
                            >
                        </span>
                    </button>
                    <div
                        v-else-if="filtered.length === 0"
                        class="px-2 py-1.5 text-xs text-muted-foreground"
                    >
                        No labels
                    </div>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
