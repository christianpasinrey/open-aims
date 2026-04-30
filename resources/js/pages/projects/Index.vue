<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CircleDashed,
    CircleDot,
    CircleSlash,
    CheckCircle2,
    PauseCircle,
    Calendar,
} from 'lucide-vue-next';

type Member = { id: number; name: string };
type Project = {
    id: number;
    name: string;
    slug: string;
    state: string | null;
    color: string | null;
    icon: string | null;
    description: string | null;
    start_date: string | null;
    target_date: string | null;
    completed_at: string | null;
    lead: { id: number; name: string; email: string } | null;
    members: Member[];
    total_issues: number;
};

defineProps<{
    projects: Project[];
    states: Record<string, string>;
}>();

function stateIcon(state: string | null) {
    switch (state) {
        case 'completed': return CheckCircle2;
        case 'started': return CircleDot;
        case 'paused': return PauseCircle;
        case 'canceled': return CircleSlash;
        case 'planned': return Calendar;
        default: return CircleDashed;
    }
}
function stateClass(state: string | null) {
    switch (state) {
        case 'completed': return 'text-emerald-500';
        case 'started': return 'text-blue-500';
        case 'paused': return 'text-amber-500';
        case 'canceled': return 'text-rose-500';
        case 'planned': return 'text-indigo-500';
        default: return 'text-zinc-500';
    }
}
function initials(name: string) {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map(p => p.charAt(0).toUpperCase())
        .join('');
}
function fmtDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <Head title="Projects" />

    <div class="flex h-full flex-1 flex-col overflow-hidden">
        <header
            class="flex shrink-0 items-center justify-between border-b border-border px-5 py-3"
        >
            <div class="flex items-center gap-2">
                <h1 class="text-[13px] font-medium">Projects</h1>
                <span class="text-[12px] text-muted-foreground">{{ projects.length }}</span>
            </div>
        </header>

        <div v-if="!projects.length" class="flex flex-1 items-center justify-center px-6 py-12 text-center">
            <p class="text-sm text-muted-foreground">No projects yet.</p>
        </div>

        <ul v-else class="flex-1 divide-y divide-border overflow-y-auto">
            <li v-for="project in projects" :key="project.id">
                <Link
                    :href="`/projects/${project.slug}`"
                    class="grid grid-cols-[auto_1fr_auto_auto_auto] items-center gap-4 px-5 py-3 hover:bg-accent/50"
                >
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-md text-[11px] font-medium text-white"
                        :style="{ backgroundColor: project.color || '#6366f1' }"
                    >
                        {{ project.name.charAt(0) }}
                    </span>

                    <div class="min-w-0">
                        <div class="truncate text-[13.5px] font-medium text-foreground">{{ project.name }}</div>
                        <div
                            v-if="project.description"
                            class="mt-0.5 truncate text-[12px] text-muted-foreground"
                        >{{ project.description }}</div>
                    </div>

                    <div class="hidden items-center gap-1.5 text-[12px] md:flex">
                        <component
                            :is="stateIcon(project.state)"
                            :class="['size-3.5', stateClass(project.state)]"
                        />
                        <span class="text-foreground capitalize">{{ project.state ?? 'backlog' }}</span>
                    </div>

                    <div class="hidden text-[11.5px] text-muted-foreground md:block">
                        {{ project.total_issues }} issue{{ project.total_issues === 1 ? '' : 's' }}
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <span
                            v-if="project.lead"
                            class="flex size-6 items-center justify-center rounded-full bg-muted text-[10px] font-medium"
                            :title="`Lead · ${project.lead.name}`"
                        >
                            {{ initials(project.lead.name) }}
                        </span>
                        <span
                            v-if="project.target_date"
                            class="hidden text-[11px] text-muted-foreground sm:inline"
                            :title="`Target ${project.target_date}`"
                        >
                            {{ fmtDate(project.target_date) }}
                        </span>
                    </div>
                </Link>
            </li>
        </ul>
    </div>
</template>
