<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Diamond, Plus } from 'lucide-vue-next';
import { computed } from 'vue';

export type ProjectMilestone = {
    id: number;
    name: string;
    description: string | null;
    target_date: string | null;
    completed_at: string | null;
    issue_count: number;
    completed_count: number;
    percent: number;
};

const props = defineProps<{
    projectSlug: string;
    color: string | null;
    milestones: ProjectMilestone[];
}>();

const emit = defineEmits<{ create: [] }>();

const accent = computed(() => props.color || '#6366f1');

function fmtDate(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function isPast(iso: string): boolean {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    return new Date(iso) < today;
}

const rows = computed(() =>
    props.milestones.map((ms) => {
        let status: 'completed' | 'overdue' | 'scheduled' | 'unscheduled' =
            'unscheduled';

        if (ms.completed_at) {
            status = 'completed';
        } else if (ms.target_date && isPast(ms.target_date)) {
            status = 'overdue';
        } else if (ms.target_date) {
            status = 'scheduled';
        }

        return { ...ms, status };
    }),
);
</script>

<template>
    <div class="mx-auto w-full max-w-3xl px-4 py-6 sm:px-8 sm:py-8">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h2
                class="text-[12px] font-medium tracking-wide text-muted-foreground uppercase"
            >
                Milestones
                <span class="ml-1 tabular-nums">{{ milestones.length }}</span>
            </h2>
            <button
                type="button"
                class="inline-flex items-center gap-1 rounded-md px-2 py-1.5 text-[12px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                @click="emit('create')"
            >
                <Plus class="size-3.5" />
                New milestone
            </button>
        </div>

        <ul
            v-if="rows.length"
            class="divide-y divide-border rounded-md border border-border"
        >
            <li v-for="ms in rows" :key="ms.id">
                <Link
                    :href="`/projects/${projectSlug}/milestones/${ms.id}`"
                    class="flex flex-col gap-2 px-3 py-3 transition-colors hover:bg-accent/40 sm:flex-row sm:items-center sm:gap-4"
                >
                    <div class="flex min-w-0 flex-1 items-start gap-2">
                        <Diamond
                            class="mt-0.5 size-3.5 shrink-0"
                            :style="{
                                color: accent,
                                fill:
                                    ms.status === 'completed'
                                        ? accent
                                        : 'transparent',
                            }"
                        />
                        <div class="min-w-0">
                            <div
                                class="truncate text-[13px] font-medium text-foreground"
                            >
                                {{ ms.name }}
                            </div>
                            <p
                                v-if="ms.description"
                                class="mt-0.5 line-clamp-2 text-[12px] text-muted-foreground"
                            >
                                {{ ms.description }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pl-5 sm:w-52 sm:pl-0">
                        <div
                            class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            :aria-valuenow="ms.percent"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-label="`${ms.name} progress`"
                        >
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    width: `${ms.percent}%`,
                                    backgroundColor: accent,
                                }"
                            ></div>
                        </div>
                        <span
                            class="w-14 shrink-0 text-right text-[11.5px] text-muted-foreground tabular-nums"
                            >{{ ms.completed_count }}/{{ ms.issue_count }}</span
                        >
                    </div>

                    <div
                        class="flex shrink-0 items-center gap-1.5 pl-5 text-[12px] tabular-nums sm:w-32 sm:justify-end sm:pl-0"
                    >
                        <span
                            v-if="ms.status === 'completed'"
                            class="text-emerald-500"
                            >Completed</span
                        >
                        <template v-else-if="ms.status === 'overdue'">
                            <span class="text-rose-400">Overdue</span>
                            <span class="text-muted-foreground">{{
                                fmtDate(ms.target_date)
                            }}</span>
                        </template>
                        <span
                            v-else-if="ms.status === 'scheduled'"
                            class="text-muted-foreground"
                            >{{ fmtDate(ms.target_date) }}</span
                        >
                        <span v-else class="text-muted-foreground"
                            >No target date</span
                        >
                    </div>
                </Link>
            </li>
        </ul>

        <div
            v-else
            class="rounded-md border border-dashed border-border px-6 py-10 text-center"
        >
            <p class="text-[13px] text-muted-foreground">
                No milestones yet. Break the project into milestones to track
                progress in stages.
            </p>
            <button
                type="button"
                class="mt-4 inline-flex items-center gap-1.5 rounded-md bg-foreground px-3 py-1.5 text-[13px] font-medium text-background transition-opacity hover:opacity-90"
                @click="emit('create')"
            >
                <Plus class="size-3.5" />
                New milestone
            </button>
        </div>
    </div>
</template>
