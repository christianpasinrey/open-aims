<script setup lang="ts">
import { Check, Copy, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { nodeColor } from '@/lib/graphs';
import type { GraphNodeData } from '@/lib/graphs';

const props = defineProps<{ node: GraphNodeData }>();

const emit = defineEmits<{ close: [] }>();

const copied = ref(false);

const CHANGE_CLASSES: Record<string, string> = {
    added: 'text-emerald-500',
    modified: 'text-amber-500',
    removed: 'text-rose-400',
    read: 'text-muted-foreground',
};

const facts = computed(() =>
    [
        ['File', props.node.file],
        ['Namespace', props.node.namespace],
        [
            'Class',
            props.node.class
                ? `${props.node.class}${props.node.class_type ? ` (${props.node.class_type})` : ''}`
                : null,
        ],
        ['Function', props.node.function],
        ['Signature', props.node.signature],
        ['Visibility', props.node.visibility],
        [
            'Resource',
            props.node.resource_type
                ? `${props.node.resource_type} · ${props.node.resource_name}`
                : null,
        ],
        ['Context', props.node.context],
        ['Role', props.node.role],
        ['Graphs', props.node.graphs > 0 ? String(props.node.graphs) : null],
    ].filter(
        (row): row is [string, string] => row[1] !== null && row[1] !== '',
    ),
);

async function copyKey(): Promise<void> {
    if (typeof navigator === 'undefined' || !navigator.clipboard) {
        return;
    }

    await navigator.clipboard.writeText(props.node.key);
    copied.value = true;
    window.setTimeout(() => (copied.value = false), 1500);
}
</script>

<template>
    <aside
        class="flex max-h-full flex-col overflow-hidden rounded-lg border border-border bg-background/95 shadow-lg backdrop-blur"
        aria-label="Node details"
    >
        <header
            class="flex items-start gap-2 border-b border-border px-3 py-2.5"
        >
            <span
                class="mt-1 size-2.5 shrink-0 rounded-full"
                :style="{ backgroundColor: nodeColor(node) }"
            ></span>
            <div class="min-w-0 flex-1">
                <p class="text-[13px] font-medium break-words text-foreground">
                    {{ node.label }}
                </p>
                <p class="text-[11.5px] text-muted-foreground">
                    {{ node.kind }}
                    <span
                        v-if="node.change"
                        :class="CHANGE_CLASSES[node.change]"
                    >
                        · {{ node.change }}</span
                    >
                </p>
            </div>
            <button
                type="button"
                class="rounded p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                aria-label="Close node details"
                @click="emit('close')"
            >
                <X class="size-3.5" />
            </button>
        </header>

        <div class="min-h-0 space-y-3 overflow-y-auto px-3 py-3 text-[12.5px]">
            <button
                type="button"
                class="flex w-full items-center gap-1.5 rounded-md border border-border px-2 py-1 text-left font-mono text-[11px] break-all text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                :title="copied ? 'Copied' : 'Copy canonical key'"
                @click="copyKey"
            >
                <component
                    :is="copied ? Check : Copy"
                    class="size-3 shrink-0"
                />
                {{ node.key }}
            </button>

            <dl class="grid grid-cols-[76px_1fr] gap-x-2 gap-y-1.5">
                <template v-for="[label, value] in facts" :key="label">
                    <dt class="text-muted-foreground">{{ label }}</dt>
                    <dd
                        class="min-w-0 font-mono text-[11.5px] break-words text-foreground"
                    >
                        {{ value }}
                    </dd>
                </template>
            </dl>

            <div v-if="node.description" class="space-y-0.5">
                <p
                    class="text-[11px] tracking-wide text-muted-foreground uppercase"
                >
                    Description
                </p>
                <p class="text-foreground">{{ node.description }}</p>
            </div>
            <div v-if="node.purpose" class="space-y-0.5">
                <p
                    class="text-[11px] tracking-wide text-muted-foreground uppercase"
                >
                    Purpose
                </p>
                <p class="text-foreground">{{ node.purpose }}</p>
            </div>
            <div v-if="node.summary" class="space-y-0.5">
                <p
                    class="text-[11px] tracking-wide text-muted-foreground uppercase"
                >
                    Summary
                </p>
                <p class="text-foreground">{{ node.summary }}</p>
            </div>
        </div>
    </aside>
</template>
