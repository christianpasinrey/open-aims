<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarRange, CheckCircle2 } from 'lucide-vue-next';

type Cycle = {
    id: number;
    number: number;
    name: string;
    description: string | null;
    starts_at: string | null;
    ends_at: string | null;
    completed_at: string | null;
    is_current: boolean;
};

defineProps<{
    team: { id: number; name: string; key: string; color: string | null } | null;
    cycles: Cycle[];
}>();

function fmtDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}
</script>

<template>
    <Head title="Cycles" />

    <div class="flex h-full flex-1 flex-col overflow-hidden">
        <header class="flex shrink-0 items-center gap-2 border-b border-border px-5 py-3">
            <CalendarRange class="size-4 text-muted-foreground" />
            <h1 class="text-[13px] font-medium">{{ team ? `${team.name} · Cycles` : 'Cycles' }}</h1>
            <span class="text-[12px] text-muted-foreground">{{ cycles.length }}</span>
        </header>

        <div v-if="!cycles.length" class="flex flex-1 items-center justify-center px-6 py-12">
            <p class="text-sm text-muted-foreground">No cycles for this team.</p>
        </div>

        <ul v-else class="flex-1 divide-y divide-border overflow-y-auto">
            <li
                v-for="cycle in cycles"
                :key="cycle.id"
                class="flex items-center gap-4 px-5 py-3"
            >
                <span
                    :class="[
                        'flex size-7 shrink-0 items-center justify-center rounded-md text-[11px] font-semibold',
                        cycle.is_current
                            ? 'bg-brand text-brand-foreground'
                            : 'bg-muted text-foreground',
                    ]"
                >
                    {{ cycle.number }}
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="text-[13.5px] font-medium">{{ cycle.name }}</span>
                        <span
                            v-if="cycle.is_current"
                            class="rounded-full border border-brand/40 bg-brand/10 px-2 py-0.5 text-[10px] font-medium text-brand"
                        >Current</span>
                        <CheckCircle2
                            v-if="cycle.completed_at"
                            class="size-3.5 text-emerald-500"
                            title="Completed"
                        />
                    </div>
                    <div class="mt-0.5 text-[12px] text-muted-foreground">
                        {{ fmtDate(cycle.starts_at) }} → {{ fmtDate(cycle.ends_at) }}
                    </div>
                </div>
            </li>
        </ul>
    </div>
</template>
