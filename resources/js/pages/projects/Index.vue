<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Calendar,
    Layers,
    Plus,
    SlidersHorizontal,
    LayoutGrid,
} from 'lucide-vue-next';
import Avatar from '@/components/repo/Avatar.vue';
import ProjectIcon from '@/components/repo/ProjectIcon.vue';

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
    completed_issues: number;
    progress: number;
};

const props = defineProps<{
    projects: Project[];
    states: Record<string, string>;
    team: { id: number; name: string; key: string; color: string | null } | null;
}>();

const headerTitle = computed<string>(() =>
    props.team ? `${props.team.name} · Projects` : 'Projects',
);

function fmtDate(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

function isOverdue(target: string | null, completedAt: string | null) {
    if (!target || completedAt) return false;
    return new Date(target).getTime() < Date.now();
}

// progress ring math (size 14, r=5)
const ringR = 5;
const ringC = 2 * Math.PI * ringR;
function ringDashOffset(percent: number) {
    return ringC * (1 - Math.max(0, Math.min(100, percent)) / 100);
}
function ringStroke(percent: number, state: string | null) {
    if (state === 'canceled') return '#a1a1aa';
    if (state === 'completed' || percent >= 100) return '#10b981';
    if (percent > 0) return '#f59e0b';
    return '#a1a1aa';
}
</script>

<template>
    <Head :title="headerTitle" />

    <div class="flex h-full flex-1 flex-col overflow-hidden">
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
                <h1 class="text-[13px] font-medium">{{ headerTitle }}</h1>
            </div>
            <button
                type="button"
                class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                aria-label="New project"
            >
                <Plus class="size-3.5" />
            </button>
        </header>

        <div
            class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4"
        >
            <nav class="flex items-center gap-1 py-2 text-[12.5px]">
                <span class="rounded-md bg-accent px-2 py-1 text-foreground">All projects</span>
                <button
                    type="button"
                    class="rounded-md px-2 py-1 text-muted-foreground transition-colors hover:bg-accent/50 hover:text-foreground"
                >
                    <Layers class="size-3.5" />
                </button>
            </nav>
            <div class="flex items-center gap-1 text-muted-foreground">
                <button
                    type="button"
                    class="rounded-md p-1.5 transition-colors hover:bg-accent hover:text-foreground"
                    aria-label="Filter"
                >
                    <SlidersHorizontal class="size-3.5" />
                </button>
                <button
                    type="button"
                    class="rounded-md p-1.5 transition-colors hover:bg-accent hover:text-foreground"
                    aria-label="Display"
                >
                    <LayoutGrid class="size-3.5" />
                </button>
            </div>
        </div>

        <div
            v-if="!projects.length"
            class="flex flex-1 items-center justify-center px-6 py-12 text-center"
        >
            <p class="text-sm text-muted-foreground">No projects.</p>
        </div>

        <div v-else class="flex-1 overflow-y-auto">
            <!-- Column header row -->
            <div
                class="sticky top-0 z-10 grid grid-cols-[1fr_120px_64px_180px_110px_70px_80px] items-center gap-4 border-b border-border bg-background px-4 py-2 text-[11px] font-medium uppercase tracking-wide text-muted-foreground"
            >
                <span>Name</span>
                <span>Health</span>
                <span></span>
                <span>Lead</span>
                <span>Target date</span>
                <span class="text-right">Issues</span>
                <span class="text-right">Status</span>
            </div>

            <ul class="divide-y divide-border">
                <li v-for="project in projects" :key="project.id">
                    <Link
                        :href="`/projects/${project.slug}`"
                        class="grid grid-cols-[1fr_120px_64px_180px_110px_70px_80px] items-center gap-4 px-4 py-2 hover:bg-accent/40"
                    >
                        <div class="flex min-w-0 items-center gap-2.5">
                            <ProjectIcon
                                :icon="project.icon"
                                :color="project.color"
                                :size="18"
                            />
                            <span class="truncate text-[13px] text-foreground">{{ project.name }}</span>
                        </div>

                        <span class="inline-flex items-center gap-1.5 text-[12px] text-muted-foreground">
                            <span class="size-1.5 rounded-full bg-zinc-500"></span>
                            No updates
                        </span>

                        <span></span>

                        <div v-if="project.lead" class="flex min-w-0 items-center gap-2 text-[12.5px]">
                            <Avatar :name="project.lead.name" :email="project.lead.email" :size="18" />
                            <span class="truncate text-foreground">{{ project.lead.name }}</span>
                        </div>
                        <div v-else class="flex items-center gap-2 text-[12px] text-muted-foreground">
                            <span class="flex size-[18px] items-center justify-center rounded-full border border-dashed border-border"></span>
                            <span>No lead</span>
                        </div>

                        <span
                            v-if="project.target_date"
                            class="inline-flex items-center gap-1 text-[12px]"
                            :class="isOverdue(project.target_date, project.completed_at) ? 'text-rose-400' : 'text-muted-foreground'"
                        >
                            <Calendar class="size-3.5" />
                            {{ fmtDate(project.target_date) }}
                        </span>
                        <span v-else class="text-[12px] text-muted-foreground">—</span>

                        <span class="text-right text-[12.5px] text-muted-foreground tabular-nums">
                            {{ project.total_issues }}
                        </span>

                        <div class="flex items-center justify-end gap-1.5">
                            <svg
                                width="14"
                                height="14"
                                viewBox="0 0 14 14"
                                fill="none"
                                class="shrink-0"
                                aria-hidden="true"
                            >
                                <circle cx="7" cy="7" r="5" stroke="#3f3f46" stroke-width="1.5" fill="none" />
                                <circle
                                    cx="7"
                                    cy="7"
                                    r="5"
                                    fill="none"
                                    stroke-width="2"
                                    :stroke="ringStroke(project.progress, project.state)"
                                    :stroke-dasharray="`${ringC} ${ringC}`"
                                    :stroke-dashoffset="ringDashOffset(project.progress)"
                                    transform="rotate(-90 7 7)"
                                    stroke-linecap="butt"
                                />
                            </svg>
                            <span class="text-[12px] text-foreground tabular-nums">{{ project.progress }}%</span>
                        </div>
                    </Link>
                </li>
            </ul>
        </div>
    </div>
</template>
