<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { KIND_SIZE, nodeColor } from '@/lib/graphs';
import type { GraphData, GraphLinkData, GraphNodeData } from '@/lib/graphs';

type SimNode = GraphNodeData & { x?: number; y?: number; z?: number };
type SimLink = Omit<GraphLinkData, 'source' | 'target'> & {
    source: number | SimNode;
    target: number | SimNode;
};
type Point = { x: number; y: number; z: number };

/** The subset of the 3d-force-graph / force-graph chainable API we use. */
type GraphInstance = {
    graphData(data: { nodes: SimNode[]; links: SimLink[] }): GraphInstance;
    width(width: number): GraphInstance;
    height(height: number): GraphInstance;
    backgroundColor(color: string): GraphInstance;
    nodeId(id: string): GraphInstance;
    nodeLabel(accessor: (node: SimNode) => string): GraphInstance;
    nodeColor(accessor: (node: SimNode) => string): GraphInstance;
    nodeVal(accessor: (node: SimNode) => number): GraphInstance;
    linkColor(accessor: (link: SimLink) => string): GraphInstance;
    linkWidth(accessor: (link: SimLink) => number): GraphInstance;
    linkDirectionalArrowLength(
        accessor: (link: SimLink) => number,
    ): GraphInstance;
    linkDirectionalArrowRelPos(position: number): GraphInstance;
    onNodeClick(callback: (node: SimNode) => void): GraphInstance;
    onBackgroundClick(callback: () => void): GraphInstance;
    zoomToFit(durationMs?: number, padding?: number): GraphInstance;
    _destructor(): void;
    cameraPosition?: (
        position: Point,
        lookAt: Point,
        durationMs: number,
    ) => GraphInstance;
    centerAt?: (x: number, y: number, durationMs: number) => GraphInstance;
    zoom?: (scale: number, durationMs: number) => GraphInstance;
    linkLineDash?: (
        accessor: (link: SimLink) => number[] | null,
    ) => GraphInstance;
    nodeCanvasObjectMode?: (accessor: () => string) => GraphInstance;
    nodeCanvasObject?: (
        render: (
            node: SimNode,
            ctx: CanvasRenderingContext2D,
            scale: number,
        ) => void,
    ) => GraphInstance;
};

const props = withDefaults(
    defineProps<{
        data: GraphData;
        mode: '3d' | '2d';
        highlight?: number[];
        selectedId?: number | null;
    }>(),
    { highlight: () => [], selectedId: null },
);

const emit = defineEmits<{ select: [node: GraphNodeData | null] }>();

const mount = ref<HTMLElement | null>(null);
const loading = ref(true);
const failed = ref<string | null>(null);

let instance: GraphInstance | null = null;
let simNodes: SimNode[] = [];
let resizeObserver: ResizeObserver | null = null;
let themeObserver: MutationObserver | null = null;
let buildToken = 0;

const colors = {
    background: '#ffffff',
    foreground: '#1b1e26',
    muted: '#94a3b8',
};

const highlightSet = computed(() => new Set(props.highlight));

/** Normalise any CSS colour (e.g. `hsl(225 7% 11%)`) to `#rrggbb` for WebGL. */
function cssColor(variable: string, fallback: string): string {
    const raw = getComputedStyle(document.documentElement)
        .getPropertyValue(variable)
        .trim();

    if (!raw) {
        return fallback;
    }

    const ctx = document.createElement('canvas').getContext('2d');

    if (!ctx) {
        return fallback;
    }

    ctx.fillStyle = fallback;
    ctx.fillStyle = raw;

    return String(ctx.fillStyle);
}

function readTheme(): void {
    colors.background = cssColor('--background', '#ffffff');
    colors.foreground = cssColor('--foreground', '#1b1e26');
    colors.muted = cssColor('--muted-foreground', '#94a3b8');
}

function colorFor(node: SimNode): string {
    if (node.id === props.selectedId) {
        return colors.foreground;
    }

    if (highlightSet.value.size > 0 && !highlightSet.value.has(node.id)) {
        return `${colors.muted}55`;
    }

    return nodeColor(node);
}

function linkColorFor(link: SimLink): string {
    const source =
        typeof link.source === 'object' ? link.source.id : link.source;
    const target =
        typeof link.target === 'object' ? link.target.id : link.target;
    const dimmed =
        highlightSet.value.size > 0 &&
        !(highlightSet.value.has(source) && highlightSet.value.has(target));

    if (link.relation === 'contains') {
        return `${colors.muted}${dimmed ? '22' : '66'}`;
    }

    return `${colors.muted}${dimmed ? '33' : 'cc'}`;
}

function tooltip(node: SimNode): string {
    const lines = [
        node.label,
        node.kind + (node.context ? ` · ${node.context}` : ''),
    ];

    if (node.file && node.kind !== 'file') {
        lines.push(node.file);
    }

    return lines.map((line) => line.replace(/[<>&]/g, '')).join('<br>');
}

function drawLabel(
    node: SimNode,
    ctx: CanvasRenderingContext2D,
    scale: number,
): void {
    const important =
        node.kind === 'file' ||
        node.id === props.selectedId ||
        highlightSet.value.has(node.id);

    if (!important && scale < 1.6) {
        return;
    }

    const fontSize = Math.max(11 / scale, 1.5);
    const radius = Math.sqrt(KIND_SIZE[node.kind] ?? 3) * 4;

    ctx.font = `${fontSize}px Inter, ui-sans-serif, system-ui, sans-serif`;
    ctx.fillStyle = colors.foreground;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    ctx.fillText(node.label, node.x ?? 0, (node.y ?? 0) + radius + 2 / scale);
}

function cloneData(): { nodes: SimNode[]; links: SimLink[] } {
    const previous = new Map(simNodes.map((node) => [node.id, node]));

    simNodes = props.data.nodes.map((node) => {
        const old = previous.get(node.id);

        return { ...node, x: old?.x, y: old?.y, z: old?.z };
    });

    return {
        nodes: simNodes,
        links: props.data.links.map((link) => ({ ...link })),
    };
}

function destroy(): void {
    instance?._destructor();
    instance = null;

    mount.value?.replaceChildren();
}

async function build(): Promise<void> {
    const token = ++buildToken;
    const element = mount.value;

    if (!element) {
        return;
    }

    loading.value = true;
    failed.value = null;

    try {
        const created =
            props.mode === '3d'
                ? new (await import('3d-force-graph')).default(element, {
                      controlType: 'orbit',
                  })
                : new (await import('force-graph')).default(element);

        if (token !== buildToken) {
            (created as unknown as GraphInstance)._destructor();

            return;
        }

        destroy();
        readTheme();

        const graph = created as unknown as GraphInstance;
        graph
            .width(element.clientWidth)
            .height(element.clientHeight)
            .backgroundColor(colors.background)
            .nodeId('id')
            .nodeLabel(tooltip)
            .nodeColor(colorFor)
            .nodeVal((node) => KIND_SIZE[node.kind] ?? 3)
            .linkColor(linkColorFor)
            .linkWidth((link) =>
                link.relation === 'contains'
                    ? 0.3
                    : Math.min(4, 0.6 + link.weight * 0.6),
            )
            .linkDirectionalArrowLength((link) =>
                link.relation === 'contains' ? 0 : 3.5,
            )
            .linkDirectionalArrowRelPos(1)
            .onNodeClick((node) => {
                emit(
                    'select',
                    props.data.nodes.find(
                        (candidate) => candidate.id === node.id,
                    ) ?? null,
                );
            })
            .onBackgroundClick(() => emit('select', null));

        if (props.mode === '2d') {
            graph.linkLineDash?.((link) =>
                link.relation === 'contains' ? [2, 2] : null,
            );
            graph.nodeCanvasObjectMode?.(() => 'after');
            graph.nodeCanvasObject?.(drawLabel);
        }

        graph.graphData(cloneData());
        instance = graph;
        window.setTimeout(() => instance?.zoomToFit(600, 40), 900);
    } catch (error) {
        failed.value =
            error instanceof Error
                ? error.message
                : 'The graph could not be drawn.';
    } finally {
        if (token === buildToken) {
            loading.value = false;
        }
    }
}

function refreshStyles(): void {
    instance?.nodeColor(colorFor).linkColor(linkColorFor);
}

function fit(): void {
    instance?.zoomToFit(600, 40);
}

function focusNode(id: number): void {
    const node = simNodes.find((candidate) => candidate.id === id);

    if (!instance || !node) {
        return;
    }

    const x = node.x ?? 0;
    const y = node.y ?? 0;
    const z = node.z ?? 0;

    if (props.mode === '3d' && instance.cameraPosition) {
        const ratio = 1 + 90 / Math.max(1, Math.hypot(x, y, z));
        instance.cameraPosition(
            { x: x * ratio, y: y * ratio, z: z * ratio },
            { x, y, z },
            800,
        );

        return;
    }

    instance.centerAt?.(x, y, 600);
    instance.zoom?.(4, 600);
}

defineExpose({ fit, focusNode });

onMounted(() => {
    readTheme();
    void build();

    resizeObserver = new ResizeObserver(() => {
        if (instance && mount.value) {
            instance
                .width(mount.value.clientWidth)
                .height(mount.value.clientHeight);
        }
    });

    if (mount.value) {
        resizeObserver.observe(mount.value);
    }

    themeObserver = new MutationObserver(() => {
        readTheme();
        instance?.backgroundColor(colors.background);
        refreshStyles();
    });
    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
});

onBeforeUnmount(() => {
    buildToken++;
    resizeObserver?.disconnect();
    themeObserver?.disconnect();
    destroy();
});

watch(
    () => props.mode,
    () => {
        simNodes = [];
        void build();
    },
);

watch(
    () => props.data,
    () => instance?.graphData(cloneData()),
);

watch([() => props.highlight, () => props.selectedId], refreshStyles);
</script>

<template>
    <div class="relative size-full min-h-[280px] overflow-hidden">
        <div ref="mount" class="absolute inset-0"></div>
        <div
            v-if="loading"
            class="pointer-events-none absolute inset-0 flex items-center justify-center text-[12.5px] text-muted-foreground"
        >
            Drawing graph…
        </div>
        <div
            v-else-if="failed"
            class="absolute inset-0 flex items-center justify-center px-6 text-center text-[12.5px] text-muted-foreground"
        >
            {{ failed }}
        </div>
    </div>
</template>
