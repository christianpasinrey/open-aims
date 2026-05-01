<script setup lang="ts">
import { computed } from 'vue';

type Point = {
    date: string;
    scope: number;
    started: number;
    completed: number;
    projected?: boolean;
};
type Ideal = { date: string; value: number };
type Burndown = {
    points: Point[];
    ideal: Ideal[];
    finalScope: number;
};

const props = defineProps<{
    burndown: Burndown;
    width?: number;
    height?: number;
}>();

const W = computed<number>(() => props.width ?? 760);
const H = computed<number>(() => props.height ?? 200);

type Series = { date: string; value: number; projected?: boolean };

const max = computed<number>(() => {
    const points = props.burndown.points;
    return Math.max(props.burndown.finalScope, ...points.map((p) => p.scope), 1);
});

function path(series: Series[]): string {
    if (series.length < 2) return '';
    const stepX = W.value / (series.length - 1);
    return series
        .map((p, i) => {
            const x = i * stepX;
            const y = H.value - (max.value <= 0 ? 0 : (p.value / max.value) * H.value);
            return `${i === 0 ? 'M' : 'L'}${x.toFixed(2)},${y.toFixed(2)}`;
        })
        .join(' ');
}
function area(series: Series[]): string {
    if (series.length < 2) return '';
    const stepX = W.value / (series.length - 1);
    const top = series
        .map((p, i) => {
            const x = i * stepX;
            const y = H.value - (max.value <= 0 ? 0 : (p.value / max.value) * H.value);
            return `${i === 0 ? 'M' : 'L'}${x.toFixed(2)},${y.toFixed(2)}`;
        })
        .join(' ');
    return `${top} L${W.value.toFixed(2)},${H.value} L0,${H.value} Z`;
}

const scopeSeries = computed<Series[]>(() =>
    props.burndown.points.map((p) => ({ date: p.date, value: p.scope, projected: p.projected })),
);
const startedSeries = computed<Series[]>(() =>
    props.burndown.points.map((p) => ({ date: p.date, value: p.completed + p.started, projected: p.projected })),
);
const completedSeries = computed<Series[]>(() =>
    props.burndown.points.map((p) => ({ date: p.date, value: p.completed, projected: p.projected })),
);
const idealSeries = computed<Series[]>(() => {
    return props.burndown.ideal.map((p) => ({ date: p.date, value: max.value - p.value }));
});

const todayX = computed<number | null>(() => {
    const points = props.burndown.points;
    if (points.length < 2) return null;
    let last = -1;
    points.forEach((p, i) => {
        if (!p.projected) last = i;
    });
    if (last < 0) return null;
    const stepX = W.value / (points.length - 1);
    return last * stepX;
});

function fmtDay(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

const xLabels = computed<{ x: number; label: string }[]>(() => {
    const points = props.burndown.points;
    if (points.length < 2) return [];
    const stepX = W.value / (points.length - 1);
    const out: { x: number; label: string }[] = [];
    points.forEach((p, i) => {
        if (i === 0 || i === points.length - 1 || i % 7 === 0) {
            out.push({ x: i * stepX, label: fmtDay(p.date) });
        }
    });
    return out;
});
</script>

<template>
    <div class="w-full overflow-hidden">
        <svg
            :viewBox="`0 0 ${W} ${H + 24}`"
            preserveAspectRatio="none"
            class="block w-full"
            :style="{ height: `${H + 24}px` }"
            aria-hidden="true"
        >
            <defs>
                <linearGradient id="bd-completed" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" stop-color="#6366f1" stop-opacity="0.55" />
                    <stop offset="100%" stop-color="#6366f1" stop-opacity="0" />
                </linearGradient>
                <linearGradient id="bd-started" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" stop-color="#facc15" stop-opacity="0.4" />
                    <stop offset="100%" stop-color="#facc15" stop-opacity="0" />
                </linearGradient>
                <pattern
                    id="bd-projected"
                    width="6"
                    height="6"
                    patternUnits="userSpaceOnUse"
                    patternTransform="rotate(45)"
                >
                    <line x1="0" y1="0" x2="0" y2="6" stroke="rgba(148,163,184,0.18)" stroke-width="2" />
                </pattern>
            </defs>

            <!-- Projected hatch for after-today region -->
            <rect
                v-if="todayX !== null && todayX < W"
                :x="todayX"
                y="0"
                :width="W - todayX"
                :height="H"
                fill="url(#bd-projected)"
            />

            <!-- Started area (yellow) -->
            <path :d="area(startedSeries)" fill="url(#bd-started)" />
            <!-- Completed area (indigo) -->
            <path :d="area(completedSeries)" fill="url(#bd-completed)" />

            <!-- Scope line (top white-ish) -->
            <path
                :d="path(scopeSeries)"
                stroke="rgba(241,245,249,0.55)"
                stroke-width="1.5"
                fill="none"
            />
            <!-- Started line (yellow) -->
            <path
                :d="path(startedSeries)"
                stroke="#facc15"
                stroke-width="1.75"
                fill="none"
            />
            <!-- Completed line (indigo) -->
            <path
                :d="path(completedSeries)"
                stroke="#818cf8"
                stroke-width="1.75"
                fill="none"
            />
            <!-- Ideal dashed line -->
            <path
                :d="path(idealSeries)"
                stroke="rgba(148,163,184,0.5)"
                stroke-width="1"
                stroke-dasharray="3 3"
                fill="none"
            />

            <!-- Today marker -->
            <line
                v-if="todayX !== null"
                :x1="todayX"
                :x2="todayX"
                y1="0"
                :y2="H"
                stroke="rgba(99,102,241,0.45)"
                stroke-width="1"
                stroke-dasharray="2 3"
            />

            <!-- X-axis labels -->
            <g :transform="`translate(0, ${H + 14})`">
                <text
                    v-for="(l, i) in xLabels"
                    :key="i"
                    :x="l.x"
                    y="0"
                    text-anchor="middle"
                    fill="rgba(148,163,184,0.85)"
                    font-size="10"
                    font-family="ui-sans-serif, system-ui"
                >
                    {{ l.label }}
                </text>
            </g>
        </svg>
    </div>
</template>
