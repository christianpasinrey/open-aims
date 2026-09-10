<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Waypoints } from 'lucide-vue-next';
import { computed, defineAsyncComponent, onMounted, ref, watch } from 'vue';
import { getJson } from '@/lib/graphs';
import type { MapResponse } from '@/lib/graphs';

const GraphExplorer = defineAsyncComponent(
    () => import('@/components/repo/graphs/GraphExplorer.vue'),
);

const props = defineProps<{
    filters: {
        project: string | null;
        milestone: number | null;
        context: string | null;
    };
    projects: Array<{
        slug: string;
        name: string;
        color: string | null;
        icon: string | null;
    }>;
    contexts: string[];
}>();

const map = ref<MapResponse | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

let controller: AbortController | null = null;

const query = computed(() => {
    const params = new URLSearchParams();

    if (props.filters.project) {
        params.set('project', props.filters.project);
    }

    if (props.filters.milestone) {
        params.set('milestone', String(props.filters.milestone));
    }

    if (props.filters.context) {
        params.set('context', props.filters.context);
    }

    return params.toString();
});

async function load(): Promise<void> {
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = null;

    try {
        map.value = await getJson<MapResponse>(
            `/graphs/map${query.value ? `?${query.value}` : ''}`,
            controller.signal,
        );
    } catch (caught) {
        if (caught instanceof DOMException && caught.name === 'AbortError') {
            return;
        }

        error.value =
            caught instanceof Error
                ? caught.message
                : 'Could not load the map.';
    } finally {
        loading.value = false;
    }
}

function applyFilter(name: 'project' | 'context', value: string): void {
    router.get(
        '/map',
        {
            ...(props.filters.project
                ? { project: props.filters.project }
                : {}),
            ...(props.filters.context
                ? { context: props.filters.context }
                : {}),
            [name]: value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

onMounted(load);

watch(query, load);
</script>

<template>
    <Head title="Map" />

    <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden">
        <header
            class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-2.5"
        >
            <h1
                class="inline-flex items-center gap-2 text-[13px] text-foreground"
            >
                <Waypoints class="size-4 text-muted-foreground" />
                Map
                <span
                    v-if="map"
                    class="text-[12px] text-muted-foreground tabular-nums"
                >
                    {{ map.stats.graphs }} graphs · {{ map.stats.nodes }} nodes
                </span>
            </h1>

            <div class="flex flex-wrap items-center gap-2">
                <label class="sr-only" for="map-project">Project</label>
                <select
                    id="map-project"
                    :value="filters.project ?? ''"
                    class="h-8 rounded-md border border-input bg-background px-2 text-[12.5px] text-foreground"
                    @change="
                        applyFilter(
                            'project',
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                >
                    <option value="">All projects</option>
                    <option
                        v-for="project in projects"
                        :key="project.slug"
                        :value="project.slug"
                    >
                        {{ project.name }}
                    </option>
                </select>

                <label class="sr-only" for="map-context">Context</label>
                <select
                    id="map-context"
                    :value="filters.context ?? ''"
                    class="h-8 rounded-md border border-input bg-background px-2 text-[12.5px] text-foreground"
                    @change="
                        applyFilter(
                            'context',
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                >
                    <option value="">All contexts</option>
                    <option
                        v-for="context in contexts"
                        :key="context"
                        :value="context"
                    >
                        {{ context }}
                    </option>
                </select>
            </div>
        </header>

        <main class="flex min-h-0 flex-1 flex-col px-4 py-3">
            <p
                v-if="loading && !map"
                class="text-[12.5px] text-muted-foreground"
            >
                Loading the map…
            </p>

            <p v-else-if="error" class="text-[12.5px] text-rose-400">
                Could not load the map ({{ error }}).
            </p>

            <div
                v-else-if="map && map.stats.graphs === 0"
                class="mx-auto mt-10 max-w-md rounded-md border border-dashed border-border px-6 py-10 text-center text-[13px] text-muted-foreground"
            >
                No graphs in this view yet. Every graph Claude attaches to an
                issue, milestone or project with
                <code class="font-mono text-[12px]">graphs-attach</code> joins
                this map.
            </div>

            <GraphExplorer
                v-else-if="map"
                :data="map"
                searchable
                class="min-h-0 flex-1"
            />
        </main>
    </div>
</template>
