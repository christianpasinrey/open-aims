<script setup lang="ts">
import { Box, Maximize2, Search, Square } from 'lucide-vue-next';
import { computed, defineAsyncComponent, onMounted, ref, watch } from 'vue';
import type { GraphData, GraphNodeData } from '@/lib/graphs';

const GraphCanvas = defineAsyncComponent(
    () => import('@/components/repo/graphs/GraphCanvas.vue'),
);
const GraphNodePanel = defineAsyncComponent(
    () => import('@/components/repo/graphs/GraphNodePanel.vue'),
);
const GraphLegend = defineAsyncComponent(
    () => import('@/components/repo/graphs/GraphLegend.vue'),
);

const props = withDefaults(
    defineProps<{
        data: GraphData;
        highlight?: number[];
        searchable?: boolean;
    }>(),
    { highlight: () => [], searchable: false },
);

const MODE_KEY = 'aims:graph-mode';

const mode = ref<'3d' | '2d'>('3d');
const ready = ref(false);
const selected = ref<GraphNodeData | null>(null);
const query = ref('');
const canvas = ref<{ fit: () => void; focusNode: (id: number) => void } | null>(
    null,
);

const matches = computed(() => {
    const term = query.value.trim().toLowerCase();

    if (term.length < 2) {
        return [];
    }

    return props.data.nodes
        .filter(
            (node) =>
                node.label.toLowerCase().includes(term) ||
                node.key.toLowerCase().includes(term),
        )
        .slice(0, 8);
});

function setMode(next: '3d' | '2d'): void {
    mode.value = next;

    try {
        window.localStorage.setItem(MODE_KEY, next);
    } catch {
        // ignore
    }
}

function pick(node: GraphNodeData): void {
    selected.value = node;
    query.value = '';
    canvas.value?.focusNode(node.id);
}

onMounted(() => {
    try {
        const stored = window.localStorage.getItem(MODE_KEY);

        if (stored === '3d' || stored === '2d') {
            mode.value = stored;
        } else if (window.matchMedia('(pointer: coarse)').matches) {
            // Touch devices get the lighter, label-friendly 2D view first.
            mode.value = '2d';
        }
    } catch {
        // ignore
    }

    ready.value = true;
});

watch(
    () => props.data,
    () => {
        if (
            selected.value &&
            !props.data.nodes.some((node) => node.id === selected.value?.id)
        ) {
            selected.value = null;
        }
    },
);
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <div class="flex flex-wrap items-center gap-2 pb-2">
            <div
                class="inline-flex rounded-md border border-border p-0.5"
                role="group"
                aria-label="View mode"
            >
                <button
                    type="button"
                    :aria-pressed="mode === '3d'"
                    :class="[
                        'inline-flex items-center gap-1 rounded px-2 py-1 text-[12px] transition-colors',
                        mode === '3d'
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                    @click="setMode('3d')"
                >
                    <Box class="size-3.5" />
                    3D
                </button>
                <button
                    type="button"
                    :aria-pressed="mode === '2d'"
                    :class="[
                        'inline-flex items-center gap-1 rounded px-2 py-1 text-[12px] transition-colors',
                        mode === '2d'
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                    @click="setMode('2d')"
                >
                    <Square class="size-3.5" />
                    2D
                </button>
            </div>

            <button
                type="button"
                class="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-[12px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                @click="canvas?.fit()"
            >
                <Maximize2 class="size-3.5" />
                Fit
            </button>

            <div
                v-if="searchable"
                class="relative min-w-[180px] flex-1 sm:max-w-xs"
            >
                <Search
                    class="pointer-events-none absolute top-1/2 left-2 size-3.5 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="query"
                    type="search"
                    placeholder="Find file, class or function"
                    aria-label="Find a node"
                    class="h-8 w-full rounded-md border border-input bg-transparent pr-2 pl-7 text-[12.5px] outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50"
                    @keydown.enter.prevent="matches[0] && pick(matches[0])"
                />
                <ul
                    v-if="matches.length"
                    class="absolute top-full right-0 left-0 z-20 mt-1 overflow-hidden rounded-md border border-border bg-popover shadow-lg"
                >
                    <li v-for="node in matches" :key="node.id">
                        <button
                            type="button"
                            class="flex w-full flex-col items-start px-2.5 py-1.5 text-left hover:bg-accent"
                            @click="pick(node)"
                        >
                            <span class="text-[12.5px] text-foreground">{{
                                node.label
                            }}</span>
                            <span
                                class="truncate text-[11px] text-muted-foreground"
                                >{{ node.file ?? node.key }}</span
                            >
                        </button>
                    </li>
                </ul>
            </div>

            <span
                class="ml-auto text-[11.5px] text-muted-foreground tabular-nums"
            >
                {{ data.nodes.length }} nodes · {{ data.links.length }} links
            </span>
        </div>

        <div
            class="relative min-h-0 flex-1 overflow-hidden rounded-lg border border-border"
        >
            <GraphCanvas
                v-if="ready"
                ref="canvas"
                :data="data"
                :mode="mode"
                :highlight="highlight"
                :selected-id="selected?.id ?? null"
                @select="selected = $event"
            />

            <GraphLegend
                :nodes="data.nodes"
                class="pointer-events-none absolute bottom-2 left-2 max-w-[calc(100%-1rem)]"
            />

            <GraphNodePanel
                v-if="selected"
                :node="selected"
                class="absolute right-2 bottom-2 left-2 max-h-[55%] sm:top-2 sm:left-auto sm:max-h-[calc(100%-1rem)] sm:w-80"
                @close="selected = null"
            />
        </div>
    </div>
</template>
