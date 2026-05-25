<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    MoreHorizontal,
    Pause,
    Play,
    Star,
} from 'lucide-vue-next';
import { computed } from 'vue';
import BurndownChart from '@/components/repo/cycles/BurndownChart.vue';
import { useFavourites } from '@/composables/useFavourites';

type BurndownPoint = {
    date: string;
    scope: number;
    started: number;
    completed: number;
    projected?: boolean;
};
type IdealPoint = { date: string; value: number };
type Burndown = {
    points: BurndownPoint[];
    ideal: IdealPoint[];
    finalScope: number;
} | null;

type Cycle = {
    id: number;
    number: number;
    name: string;
    description: string | null;
    starts_at: string | null;
    ends_at: string | null;
    completed_at: string | null;
    state: 'planned' | 'upcoming' | 'current' | 'completed';
    scope: number;
    started: number;
    completed: number;
    of_capacity: number;
    burndown: Burndown;
};

type Team = { id: number; name: string; key: string; color: string | null };

const props = defineProps<{
    team: Team | null;
    cycles: Cycle[];
    filters?: { team: string | null };
}>();

// ---- Favourites
const { isFavourited, toggle } = useFavourites();
const teamKey = computed<string>(() => props.team?.key ?? '');
const favouriteHref = computed<string>(() =>
    teamKey.value ? `/cycles?team=${teamKey.value}` : '/cycles',
);
const isStarred = computed<boolean>(() => isFavourited('cycles', favouriteHref.value));
function toggleStar(): void {
    if (!props.team) return;
    toggle({
        kind: 'cycles',
        href: favouriteHref.value,
        label: `${props.team.name} cycles`,
        icon: 'Calendar',
    });
}

// ---- Timeline rows: alternate cycles + cooldown gaps
type Row =
    | { kind: 'cycle'; cycle: Cycle }
    | { kind: 'cooldown'; days: number };

const rows = computed<Row[]>(() => {
    const out: Row[] = [];
    const sorted = [...props.cycles];
    for (let i = 0; i < sorted.length; i++) {
        out.push({ kind: 'cycle', cycle: sorted[i] });
        const next = sorted[i + 1];
        if (next && next.ends_at && sorted[i].starts_at) {
            const gapMs =
                new Date(sorted[i].starts_at as string).getTime() -
                new Date(next.ends_at as string).getTime();
            const days = Math.round(gapMs / 86_400_000);
            if (days > 0) {
                out.push({ kind: 'cooldown', days });
            }
        }
    }
    return out;
});

function fmtMonthDay(iso: string | null): string {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}
function fmtDay(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}
function fmtCooldown(days: number): string {
    if (days >= 7 && days % 7 === 0) {
        return `${days / 7} week${days / 7 === 1 ? '' : 's'} cooldown`;
    }
    return `${days} day${days === 1 ? '' : 's'} cooldown`;
}

function stateBadgeLabel(state: Cycle['state']): string {
    return {
        planned: 'Planned',
        upcoming: 'Upcoming',
        current: 'Current',
        completed: 'Completed',
    }[state];
}
function stateBadgeClass(state: Cycle['state']): string {
    return {
        planned: 'text-muted-foreground',
        upcoming: 'text-blue-300',
        current: 'text-foreground',
        completed: 'text-emerald-300',
    }[state];
}
function ringStrokeFor(percent: number): string {
    if (percent > 100) return '#ef4444';
    if (percent >= 70) return '#facc15';
    if (percent >= 30) return '#a855f7';
    return '#64748b';
}

// ---- Burndown chart geometry
type Series = { date: string; value: number; projected?: boolean };

function pathForSeries(series: Series[], width: number, height: number, max: number): string {
    if (series.length < 2) return '';
    const stepX = width / (series.length - 1);
    return series
        .map((p, i) => {
            const x = i * stepX;
            const y = height - (max <= 0 ? 0 : (p.value / max) * height);
            return `${i === 0 ? 'M' : 'L'}${x.toFixed(2)},${y.toFixed(2)}`;
        })
        .join(' ');
}
function areaForSeries(series: Series[], width: number, height: number, max: number): string {
    if (series.length < 2) return '';
    const stepX = width / (series.length - 1);
    const top = series
        .map((p, i) => {
            const x = i * stepX;
            const y = height - (max <= 0 ? 0 : (p.value / max) * height);
            return `${i === 0 ? 'M' : 'L'}${x.toFixed(2)},${y.toFixed(2)}`;
        })
        .join(' ');
    return `${top} L${width.toFixed(2)},${height} L0,${height} Z`;
}
type ChartGeo = {
    width: number;
    height: number;
    max: number;
    scopePath: string;
    startedPath: string;
    startedArea: string;
    completedPath: string;
    completedArea: string;
    idealPath: string;
    todayX: number | null;
    xLabels: { x: number; label: string }[];
};

function buildChart(burndown: NonNullable<Burndown>): ChartGeo {
    const width = 760;
    const height = 200;
    const points = burndown.points;
    const max = Math.max(burndown.finalScope, ...points.map((p) => p.scope), 1);

    const scopeSeries: Series[] = points.map((p) => ({ date: p.date, value: p.scope, projected: p.projected }));
    const startedSeries: Series[] = points.map((p) => ({ date: p.date, value: p.completed + p.started, projected: p.projected }));
    const completedSeries: Series[] = points.map((p) => ({ date: p.date, value: p.completed, projected: p.projected }));
    const idealSeries: Series[] = burndown.ideal.map((p) => ({ date: p.date, value: max - p.value }));

    // Today marker — last non-projected point.
    const todayIdx = (() => {
        let last = -1;
        points.forEach((p, i) => {
            if (!p.projected) {
                last = i;
            }
        });
        return last >= 0 ? last : null;
    })();
    const stepX = width / Math.max(1, points.length - 1);
    const todayX = todayIdx !== null ? todayIdx * stepX : null;

    // x-axis labels: every ~7 days
    const xLabels: { x: number; label: string }[] = [];
    points.forEach((p, i) => {
        if (i === 0 || i === points.length - 1 || i % 7 === 0) {
            xLabels.push({ x: i * stepX, label: fmtDay(p.date) });
        }
    });

    return {
        width,
        height,
        max,
        scopePath: pathForSeries(scopeSeries, width, height, max),
        startedPath: pathForSeries(startedSeries, width, height, max),
        startedArea: areaForSeries(startedSeries, width, height, max),
        completedPath: pathForSeries(completedSeries, width, height, max),
        completedArea: areaForSeries(completedSeries, width, height, max),
        idealPath: pathForSeries(idealSeries, width, height, max),
        todayX,
        xLabels,
    };
}

// ---- Per-cycle deltas for the legend
function delta(part: number, scope: number): string {
    if (scope <= 0) return '0%';
    const pct = Math.round((part / scope) * 100);
    return `${pct}%`;
}
function scopeDeltaSign(burndown: NonNullable<Burndown>): string {
    const points = burndown.points;
    if (points.length < 2) return '0%';
    const first = points.find((p) => p.scope > 0)?.scope ?? 0;
    const last = points[points.length - 1].scope;
    if (first === 0) return '0%';
    const pct = Math.round(((last - first) / first) * 100);
    return `${pct >= 0 ? '+' : ''}${pct}%`;
}
</script>

<template>
    <Head :title="team ? `${team.name} cycles` : 'Cycles'" />

    <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden">
        <!-- Header -->
        <header
            class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4 py-2.5"
        >
            <div class="flex min-w-0 items-center gap-2">
                <span
                    v-if="team"
                    class="flex size-5 items-center justify-center rounded-md text-[10px] font-semibold text-white"
                    :style="{ backgroundColor: team.color || '#6366f1' }"
                >
                    {{ team.key.charAt(0) }}
                </span>
                <span class="truncate text-[13px] font-medium text-foreground">{{
                    team?.name ?? 'Cycles'
                }}</span>
                <button
                    type="button"
                    :class="[
                        'transition-colors',
                        isStarred
                            ? 'text-amber-400 hover:text-amber-500'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                    :aria-label="isStarred ? 'Unfavourite' : 'Favourite'"
                    @click="toggleStar"
                >
                    <Star class="size-3.5" :fill="isStarred ? 'currentColor' : 'none'" />
                </button>
            </div>
        </header>

        <!-- Empty state -->
        <div
            v-if="!cycles.length"
            class="flex flex-1 items-center justify-center text-[13px] text-muted-foreground"
        >
            No cycles yet.
        </div>

        <!-- Timeline body -->
        <div v-else class="flex min-h-0 flex-1 overflow-y-auto">
            <div class="mx-auto flex w-full max-w-6xl gap-6 px-6 py-8">
                <!-- LEFT: date gutter -->
                <div class="relative w-16 shrink-0">
                    <div
                        class="absolute left-1/2 top-3 -translate-x-1/2 bottom-3 w-px bg-border/60"
                    ></div>
                    <ul class="relative space-y-[2.4rem] py-2">
                        <template v-for="(row, idx) in rows" :key="`g-${idx}`">
                            <li
                                v-if="row.kind === 'cycle' && row.cycle.starts_at"
                                class="flex flex-col items-center text-[10.5px] uppercase tracking-wider text-muted-foreground"
                            >
                                <span
                                    class="flex size-2 items-center justify-center rounded-full bg-border"
                                ></span>
                                <span class="mt-1 leading-tight text-center">
                                    {{ fmtMonthDay(row.cycle.starts_at).split(' ')[0] }}<br />
                                    <span class="text-[12px] text-foreground/80 normal-case font-medium">{{
                                        fmtMonthDay(row.cycle.starts_at).split(' ')[1]
                                    }}</span>
                                </span>
                            </li>
                            <li v-else class="h-6"></li>
                        </template>
                    </ul>
                </div>

                <!-- RIGHT: cycle rows -->
                <div class="flex min-w-0 flex-1 flex-col gap-6">
                    <template v-for="(row, idx) in rows" :key="`r-${idx}`">
                        <!-- Cooldown row -->
                        <div
                            v-if="row.kind === 'cooldown'"
                            class="flex items-center gap-2 text-[12.5px] text-muted-foreground"
                        >
                            <Pause class="size-3.5" />
                            <span>{{ fmtCooldown(row.days) }}</span>
                        </div>

                        <!-- Cycle row -->
                        <article
                            v-else
                            class="rounded-lg border border-border/60 bg-card/40"
                        >
                            <header class="flex items-center justify-between gap-3 px-4 py-3">
                                <Link
                                    :href="`/cycles/${row.cycle.number}?team=${teamKey}`"
                                    class="flex min-w-0 items-center gap-2"
                                >
                                    <Play
                                        class="size-3.5 fill-muted-foreground/80 text-muted-foreground/80"
                                    />
                                    <span class="text-[14px] font-medium text-foreground">
                                        {{ row.cycle.name || `Cycle ${row.cycle.number}` }}
                                    </span>
                                </Link>
                                <div class="flex items-center gap-3">
                                    <span
                                        :class="[
                                            'rounded-md border border-border/60 bg-background/60 px-1.5 py-0.5 text-[11px] font-medium',
                                            stateBadgeClass(row.cycle.state),
                                        ]"
                                    >
                                        {{ stateBadgeLabel(row.cycle.state) }}
                                    </span>
                                    <div class="flex items-center gap-1.5">
                                        <svg
                                            class="size-4"
                                            viewBox="0 0 16 16"
                                            aria-hidden="true"
                                        >
                                            <circle
                                                cx="8"
                                                cy="8"
                                                r="6"
                                                fill="none"
                                                stroke="var(--border)"
                                                stroke-width="1.5"
                                            />
                                            <circle
                                                cx="8"
                                                cy="8"
                                                r="6"
                                                fill="none"
                                                :stroke="ringStrokeFor(row.cycle.of_capacity)"
                                                stroke-width="1.5"
                                                :stroke-dasharray="`${Math.min(row.cycle.of_capacity, 100) * 0.377} 100`"
                                                stroke-linecap="round"
                                                transform="rotate(-90 8 8)"
                                            />
                                        </svg>
                                        <span class="text-[12.5px] text-muted-foreground">
                                            <span
                                                :class="[
                                                    'font-medium',
                                                    row.cycle.of_capacity > 100
                                                        ? 'text-red-400'
                                                        : 'text-foreground',
                                                ]"
                                                >{{ row.cycle.of_capacity }}%</span
                                            >
                                            of capacity
                                        </span>
                                    </div>
                                    <span class="text-[12.5px] text-muted-foreground">
                                        <span class="font-medium text-foreground">{{
                                            row.cycle.scope
                                        }}</span>
                                        scope
                                    </span>
                                    <button
                                        type="button"
                                        class="rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                    >
                                        <MoreHorizontal class="size-3.5" />
                                    </button>
                                </div>
                            </header>

                            <!-- Burndown chart for the current cycle -->
                            <div
                                v-if="row.cycle.burndown"
                                class="grid grid-cols-[1fr_180px] gap-6 border-t border-border/40 px-4 pt-4 pb-5"
                            >
                                <BurndownChart :burndown="row.cycle.burndown" />
                                <ul class="flex flex-col gap-2 self-center text-[12.5px]">
                                    <li class="flex items-center justify-between gap-2">
                                        <span class="flex items-center gap-2 text-muted-foreground">
                                            <span class="size-2 rounded-sm bg-foreground/70"></span>
                                            Scope
                                        </span>
                                        <span class="text-foreground">
                                            {{ row.cycle.scope }}
                                            <span
                                                :class="
                                                    scopeDeltaSign(row.cycle.burndown).startsWith('-')
                                                        ? 'ml-1 text-emerald-400'
                                                        : 'ml-1 text-red-400'
                                                "
                                                >{{ scopeDeltaSign(row.cycle.burndown) }}</span
                                            >
                                        </span>
                                    </li>
                                    <li class="flex items-center justify-between gap-2">
                                        <span class="flex items-center gap-2 text-muted-foreground">
                                            <span class="size-2 rounded-sm bg-yellow-400"></span>
                                            Started
                                        </span>
                                        <span class="text-foreground">
                                            {{ row.cycle.started }}
                                            <span class="ml-1 text-muted-foreground">
                                                · {{ delta(row.cycle.started, row.cycle.scope) }}
                                            </span>
                                        </span>
                                    </li>
                                    <li class="flex items-center justify-between gap-2">
                                        <span class="flex items-center gap-2 text-muted-foreground">
                                            <span class="size-2 rounded-sm bg-indigo-400"></span>
                                            Completed
                                        </span>
                                        <span class="text-foreground">
                                            {{ row.cycle.completed }}
                                            <span class="ml-1 text-muted-foreground">
                                                · {{ delta(row.cycle.completed, row.cycle.scope) }}
                                            </span>
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </article>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
