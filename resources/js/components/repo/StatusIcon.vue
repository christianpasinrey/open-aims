<script setup lang="ts">
import { computed } from 'vue';

type Type =
    | 'triage'
    | 'backlog'
    | 'unstarted'
    | 'started'
    | 'completed'
    | 'canceled';

const props = withDefaults(
    defineProps<{
        type?: Type | string | null;
        color?: string | null;
        progress?: number;
        size?: number;
        title?: string;
    }>(),
    { type: 'unstarted', size: 14 },
);

const t = computed<Type>(() => {
    const v = (props.type ?? 'unstarted') as Type;
    return [
        'triage',
        'backlog',
        'unstarted',
        'started',
        'completed',
        'canceled',
    ].includes(v as string)
        ? v
        : 'unstarted';
});

const fallbackColor = computed<string>(() => {
    switch (t.value) {
        case 'triage':
            return '#fbbf24';
        case 'backlog':
            return '#94a3b8';
        case 'unstarted':
            return '#94a3b8';
        case 'started':
            return '#f59e0b';
        case 'completed':
            return '#10b981';
        case 'canceled':
            return '#94a3b8';
        default:
            return '#94a3b8';
    }
});

const stroke = computed<string>(() => props.color ?? fallbackColor.value);
const fill = computed<string>(() => props.color ?? fallbackColor.value);

// Started state: circle with arc fill based on progress (default 25%)
const startedDashArray = 2 * Math.PI * 5; // r=5
const startedProgress = computed<number>(() =>
    typeof props.progress === 'number'
        ? Math.max(0, Math.min(1, props.progress))
        : 0.35,
);
const startedDashOffset = computed<number>(
    () => startedDashArray * (1 - startedProgress.value),
);
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 14 14"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        aria-hidden="true"
        role="img"
        :title="title"
        class="shrink-0"
    >
        <!-- TRIAGE: dashed amber circle with center dot -->
        <template v-if="t === 'triage'">
            <circle
                cx="7"
                cy="7"
                r="5.5"
                :stroke="stroke"
                stroke-width="1.5"
                stroke-dasharray="1.6 1.6"
            />
            <path
                d="M7 4.2 v2.8 M7 9 v0.4"
                :stroke="stroke"
                stroke-width="1.6"
                stroke-linecap="round"
            />
        </template>

        <!-- BACKLOG: dashed grey circle -->
        <template v-else-if="t === 'backlog'">
            <circle
                cx="7"
                cy="7"
                r="5.5"
                :stroke="stroke"
                stroke-width="1.5"
                stroke-dasharray="1.6 1.6"
            />
        </template>

        <!-- UNSTARTED / TODO: empty solid circle -->
        <template v-else-if="t === 'unstarted'">
            <circle
                cx="7"
                cy="7"
                r="5.5"
                :stroke="stroke"
                stroke-width="1.5"
                fill="none"
            />
        </template>

        <!-- STARTED: circle with arc-progress fill -->
        <template v-else-if="t === 'started'">
            <circle
                cx="7"
                cy="7"
                r="5.5"
                :stroke="stroke"
                stroke-width="1.5"
                fill="none"
                opacity="0.35"
            />
            <circle
                cx="7"
                cy="7"
                r="5"
                :stroke="stroke"
                stroke-width="4"
                fill="none"
                :stroke-dasharray="`${startedDashArray} ${startedDashArray}`"
                :stroke-dashoffset="startedDashOffset"
                transform="rotate(-90 7 7)"
                stroke-linecap="butt"
            />
        </template>

        <!-- COMPLETED: filled circle with check -->
        <template v-else-if="t === 'completed'">
            <circle cx="7" cy="7" r="6" :fill="fill" />
            <path
                d="M4.4 7.2 L6 8.7 L9.6 5.1"
                stroke="white"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
                fill="none"
            />
        </template>

        <!-- CANCELED: filled circle with X -->
        <template v-else-if="t === 'canceled'">
            <circle cx="7" cy="7" r="6" :fill="fill" />
            <path
                d="M5 5 L9 9 M9 5 L5 9"
                stroke="white"
                stroke-width="1.5"
                stroke-linecap="round"
            />
        </template>
    </svg>
</template>
