<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Radar, Waypoints } from 'lucide-vue-next';
import { computed, defineAsyncComponent, onMounted, ref, watch } from 'vue';
import { getJson, STAGE_CLASSES } from '@/lib/graphs';
import type { ImpactResponse, OwnerGraphsResponse } from '@/lib/graphs';

const GraphExplorer = defineAsyncComponent(
    () => import('@/components/repo/graphs/GraphExplorer.vue'),
);

const props = defineProps<{
    ownerType: 'issue' | 'project' | 'milestone';
    ownerId: number;
    mapHref?: string | null;
}>();

const loading = ref(true);
const error = ref<string | null>(null);
const response = ref<OwnerGraphsResponse | null>(null);
const selectedId = ref<number | null>(null);

const impact = ref<ImpactResponse | null>(null);
const impactLoading = ref(false);
const impactError = ref<string | null>(null);

const graphs = computed(() => response.value?.graphs ?? []);
const selected = computed(
    () =>
        graphs.value.find((graph) => graph.id === selectedId.value) ??
        graphs.value[0] ??
        null,
);
const highlight = computed(() =>
    impact.value
        ? [
              ...impact.value.seeds.map((node) => node.id),
              ...impact.value.affected.map((node) => node.id),
          ]
        : [],
);

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        response.value = await getJson<OwnerGraphsResponse>(
            `/graphs/owners/${props.ownerType}/${props.ownerId}`,
        );
        selectedId.value = response.value.graphs[0]?.id ?? null;
    } catch (caught) {
        error.value =
            caught instanceof Error ? caught.message : 'Could not load graphs.';
    } finally {
        loading.value = false;
    }
}

async function toggleImpact(): Promise<void> {
    if (impact.value) {
        impact.value = null;

        return;
    }

    if (!selected.value) {
        return;
    }

    impactLoading.value = true;
    impactError.value = null;

    try {
        impact.value = await getJson<ImpactResponse>(
            `/graphs/${selected.value.id}/impact`,
        );
    } catch (caught) {
        impactError.value =
            caught instanceof Error ? caught.message : 'Could not load impact.';
    } finally {
        impactLoading.value = false;
    }
}

onMounted(load);

watch(selectedId, () => {
    impact.value = null;
});

watch(
    () => [props.ownerType, props.ownerId],
    () => {
        impact.value = null;
        void load();
    },
);
</script>

<template>
    <section aria-labelledby="owner-graphs-title">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2
                id="owner-graphs-title"
                class="inline-flex items-center gap-1.5 text-[12px] font-medium tracking-wide text-muted-foreground uppercase"
            >
                <Waypoints class="size-3.5" />
                Graphs
                <span v-if="graphs.length" class="tabular-nums">{{
                    graphs.length
                }}</span>
            </h2>
            <Link
                v-if="mapHref && graphs.length"
                :href="mapHref"
                class="text-[12px] text-muted-foreground hover:text-foreground hover:underline"
            >
                See it in the map
            </Link>
        </div>

        <p v-if="loading" class="text-[12.5px] text-muted-foreground">
            Loading graphs…
        </p>

        <p v-else-if="error" class="text-[12.5px] text-rose-400">
            Could not load graphs ({{ error }}).
        </p>

        <div
            v-else-if="!graphs.length"
            class="rounded-md border border-dashed border-border px-3 py-4 text-[12.5px] text-muted-foreground"
        >
            No graphs yet. Claude attaches them over MCP with
            <code class="font-mono text-[11.5px]">graphs-attach</code> — a
            <em>planned</em> graph before coding and an <em>implemented</em> one
            when the work is done.
        </div>

        <template v-else-if="selected">
            <div
                class="mb-2 flex flex-wrap gap-1.5"
                role="tablist"
                aria-label="Graphs"
            >
                <button
                    v-for="graph in graphs"
                    :key="graph.id"
                    type="button"
                    role="tab"
                    :aria-selected="graph.id === selected.id"
                    :class="[
                        'inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-[12px] transition-colors',
                        graph.id === selected.id
                            ? 'border-foreground/30 bg-accent text-foreground'
                            : 'border-border text-muted-foreground hover:text-foreground',
                    ]"
                    @click="selectedId = graph.id"
                >
                    <span class="max-w-[220px] truncate">{{
                        graph.title
                    }}</span>
                    <span
                        :class="[
                            'rounded border px-1 text-[10.5px]',
                            STAGE_CLASSES[graph.stage],
                        ]"
                        >{{ graph.stage }}</span
                    >
                    <span class="text-[10.5px] tabular-nums"
                        >v{{ graph.version }}</span
                    >
                </button>
            </div>

            <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
                <p class="max-w-prose text-[12.5px] text-muted-foreground">
                    {{ selected.summary }}
                    <span class="font-mono text-[11px]"
                        >· {{ selected.repo }}@{{ selected.ref }}</span
                    >
                </p>
                <button
                    type="button"
                    :aria-pressed="impact !== null"
                    :disabled="impactLoading"
                    :class="[
                        'inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-[12px] transition-colors disabled:opacity-50',
                        impact
                            ? 'border-amber-500/40 bg-amber-500/10 text-foreground'
                            : 'border-border text-muted-foreground hover:bg-accent hover:text-foreground',
                    ]"
                    @click="toggleImpact"
                >
                    <Radar class="size-3.5" />
                    {{
                        impactLoading
                            ? 'Analysing…'
                            : impact
                              ? 'Hide impact'
                              : 'Show impact'
                    }}
                </button>
            </div>

            <div class="h-[420px] lg:h-[520px]">
                <GraphExplorer
                    :data="selected"
                    :highlight="highlight"
                    searchable
                />
            </div>

            <p v-if="impactError" class="mt-2 text-[12.5px] text-rose-400">
                Could not load impact ({{ impactError }}).
            </p>

            <div
                v-if="impact"
                class="mt-3 grid gap-3 rounded-md border border-border bg-card/40 p-3 text-[12.5px] sm:grid-cols-2"
            >
                <div>
                    <p
                        class="text-[11px] tracking-wide text-muted-foreground uppercase"
                    >
                        Affected
                    </p>
                    <p class="text-foreground">
                        <span class="font-medium tabular-nums">{{
                            impact.summary.affected
                        }}</span>
                        nodes from
                        <span class="tabular-nums">{{
                            impact.summary.seeds
                        }}</span>
                        changed
                        <span
                            v-if="Object.keys(impact.summary.by_role).length"
                            class="text-muted-foreground"
                        >
                            ·
                            {{
                                Object.entries(impact.summary.by_role)
                                    .map(([role, count]) => `${count} ${role}`)
                                    .join(', ')
                            }}
                        </span>
                    </p>
                </div>

                <div v-if="impact.tests_to_run.length">
                    <p
                        class="text-[11px] tracking-wide text-muted-foreground uppercase"
                    >
                        Tests to run
                    </p>
                    <ul class="space-y-0.5">
                        <li
                            v-for="test in impact.tests_to_run"
                            :key="test.key"
                            class="truncate font-mono text-[11.5px] text-foreground"
                        >
                            {{ test.label }}
                        </li>
                    </ul>
                </div>

                <div v-if="impact.collisions.length" class="sm:col-span-2">
                    <p
                        class="text-[11px] tracking-wide text-muted-foreground uppercase"
                    >
                        Open work touching the same code
                    </p>
                    <ul class="space-y-1">
                        <li
                            v-for="collision in impact.collisions"
                            :key="collision.graph_id"
                        >
                            <Link
                                v-if="collision.owner"
                                :href="collision.owner.url"
                                class="text-foreground hover:underline"
                                >{{ collision.owner.identifier }} ·
                                {{ collision.owner.name }}</Link
                            >
                            <span class="text-muted-foreground">
                                —
                                {{
                                    collision.shared
                                        .map((item) => item.label)
                                        .join(', ')
                                }}
                            </span>
                        </li>
                    </ul>
                </div>

                <div
                    v-if="impact.signature_changes.length"
                    class="sm:col-span-2"
                >
                    <p
                        class="text-[11px] tracking-wide text-muted-foreground uppercase"
                    >
                        Signature changes
                    </p>
                    <ul class="space-y-1 font-mono text-[11.5px]">
                        <li
                            v-for="change in impact.signature_changes"
                            :key="change.key"
                        >
                            <span class="text-rose-400 line-through">{{
                                change.from
                            }}</span>
                            <br />
                            <span class="text-emerald-500">{{
                                change.to
                            }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </template>
    </section>
</template>
