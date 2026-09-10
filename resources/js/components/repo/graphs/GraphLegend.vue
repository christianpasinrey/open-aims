<script setup lang="ts">
import { computed } from 'vue';
import { CONTEXT_COLORS, RESOURCE_COLOR } from '@/lib/graphs';
import type { GraphNodeData } from '@/lib/graphs';

const props = defineProps<{ nodes: GraphNodeData[] }>();

const contexts = computed(() => {
    const present = new Set(
        props.nodes
            .filter((node) => node.kind !== 'resource')
            .map((node) => node.context ?? 'other'),
    );

    return Object.keys(CONTEXT_COLORS).filter((context) =>
        present.has(context),
    );
});

const hasResources = computed(() =>
    props.nodes.some((node) => node.kind === 'resource'),
);
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md border border-border bg-background/90 px-2.5 py-1.5 text-[11px] text-muted-foreground backdrop-blur"
        aria-label="Graph legend"
    >
        <span
            v-for="context in contexts"
            :key="context"
            class="inline-flex items-center gap-1"
        >
            <span
                class="size-2 rounded-full"
                :style="{ backgroundColor: CONTEXT_COLORS[context] }"
            ></span>
            {{ context }}
        </span>
        <span v-if="hasResources" class="inline-flex items-center gap-1">
            <span
                class="size-2 rounded-full"
                :style="{ backgroundColor: RESOURCE_COLOR }"
            ></span>
            resource
        </span>
        <span class="text-muted-foreground/80"
            >size: file › class › function</span
        >
    </div>
</template>
