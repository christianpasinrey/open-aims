<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, SlidersHorizontal } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Avatar from '@/components/repo/Avatar.vue';
import PriorityIcon from '@/components/repo/PriorityIcon.vue';
import ProjectIcon from '@/components/repo/ProjectIcon.vue';
import StatusIcon from '@/components/repo/StatusIcon.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { startedProgressByState } from '@/lib/states';

type State = {
    id: number;
    name: string;
    type: string;
    color: string;
    position: number;
};
type Label = { id: number; name: string; color?: string | null };
type Project = {
    id: number;
    name: string;
    slug?: string | null;
    color?: string | null;
    icon?: string | null;
};
type Member = { id: number; name: string; email: string };

const props = defineProps<{
    teamKey: string | null;
    states: State[];
    labels: Label[];
    projects: Project[];
    priorities: Record<string, string>;
    filters: {
        team: string | null;
        assignee: string | null;
        state: string | null;
        priority: number | null;
        project: number | null;
        labels: number[];
        group: string;
        sort: string;
    };
}>();

const startedProgress = computed(() => startedProgressByState(props.states));

// State lifecycle order, same as the page list.
const TYPE_RANK: Record<string, number> = {
    triage: 0,
    started: 1,
    unstarted: 2,
    backlog: 3,
    completed: 4,
    canceled: 5,
};
const orderedStates = computed(() =>
    [...props.states].sort((a, b) => {
        const ta = TYPE_RANK[a.type] ?? 99;
        const tb = TYPE_RANK[b.type] ?? 99;

        if (ta !== tb) {
            return ta - tb;
        }

        return a.position - b.position;
    }),
);

const members = ref<Member[]>([]);
const membersLoaded = ref(false);
const membersLoading = ref(false);

async function loadMembers(): Promise<void> {
    if (membersLoaded.value || membersLoading.value) {
        return;
    }

    membersLoading.value = true;

    try {
        const res = await fetch('/workspace/members', {
            headers: { Accept: 'application/json' },
        });

        if (!res.ok) {
            return;
        }

        const json = (await res.json()) as { data?: Member[] };
        members.value = json.data ?? [];
        membersLoaded.value = true;
    } catch {
        // network failures are non-fatal — picker just stays empty.
    } finally {
        membersLoading.value = false;
    }
}

function navigate(
    patch: Record<string, string | number | null | undefined>,
): void {
    const params: Record<string, string> = {};

    if (props.filters.team) {
        params.team = props.filters.team;
    }

    if (props.filters.assignee) {
        params.assignee = props.filters.assignee;
    }

    if (props.filters.state) {
        params.state = props.filters.state;
    }

    if (
        props.filters.priority !== null &&
        props.filters.priority !== undefined
    ) {
        params.priority = String(props.filters.priority);
    }

    if (props.filters.project !== null && props.filters.project !== undefined) {
        params.project = String(props.filters.project);
    }

    if (props.filters.labels.length) {
        params.labels = props.filters.labels.join(',');
    }

    if (props.filters.group && props.filters.group !== 'status') {
        params.group = props.filters.group;
    }

    if (props.filters.sort && props.filters.sort !== 'priority') {
        params.sort = props.filters.sort;
    }

    for (const [k, v] of Object.entries(patch)) {
        if (v === null || v === undefined || v === '') {
            delete params[k];
        } else {
            params[k] = String(v);
        }
    }

    router.get('/issues', params, {
        preserveState: false,
        preserveScroll: true,
    });
}

function toggleLabel(id: number): void {
    const current = new Set(props.filters.labels);

    if (current.has(id)) {
        current.delete(id);
    } else {
        current.add(id);
    }

    const next = [...current];
    navigate({ labels: next.length ? next.join(',') : null });
}

const priorityOrder = [1, 2, 3, 4, 0]; // Urgent, High, Medium, Low, No priority

const activeFilterCount = computed(() => {
    let n = 0;

    if (props.filters.state) {
        n++;
    }

    if (props.filters.priority !== null) {
        n++;
    }

    if (props.filters.assignee) {
        n++;
    }

    if (props.filters.project) {
        n++;
    }

    if (props.filters.labels.length) {
        n++;
    }

    return n;
});
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="relative rounded-md p-1.5 transition-colors hover:bg-accent hover:text-foreground"
                :class="
                    activeFilterCount > 0
                        ? 'text-foreground'
                        : 'text-muted-foreground'
                "
                aria-label="Filter"
                title="Filter"
            >
                <SlidersHorizontal class="size-3.5" />
                <span
                    v-if="activeFilterCount > 0"
                    class="absolute -top-0.5 -right-0.5 inline-flex size-3.5 items-center justify-center rounded-full bg-brand text-[9px] font-semibold text-white"
                    >{{ activeFilterCount }}</span
                >
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-52">
            <DropdownMenuLabel>Filter by</DropdownMenuLabel>
            <DropdownMenuSeparator />

            <!-- Status -->
            <DropdownMenuSub>
                <DropdownMenuSubTrigger>Status</DropdownMenuSubTrigger>
                <DropdownMenuSubContent class="max-h-72 w-56 overflow-y-auto">
                    <DropdownMenuItem
                        v-for="s in orderedStates"
                        :key="s.id"
                        @select="navigate({ state: s.type })"
                    >
                        <StatusIcon
                            :type="s.type"
                            :color="s.color"
                            :progress="startedProgress[s.id]"
                            :size="14"
                        />
                        <span class="flex-1 truncate">{{ s.name }}</span>
                        <Check
                            v-if="filters.state === s.type"
                            class="size-3.5 text-foreground"
                        />
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        :disabled="!filters.state"
                        @select="navigate({ state: null })"
                    >
                        <span class="text-muted-foreground">Clear</span>
                    </DropdownMenuItem>
                </DropdownMenuSubContent>
            </DropdownMenuSub>

            <!-- Priority -->
            <DropdownMenuSub>
                <DropdownMenuSubTrigger>Priority</DropdownMenuSubTrigger>
                <DropdownMenuSubContent class="w-56">
                    <DropdownMenuItem
                        v-for="p in priorityOrder"
                        :key="p"
                        @select="navigate({ priority: p })"
                    >
                        <PriorityIcon :priority="p" :size="14" />
                        <span class="flex-1">{{
                            priorities[String(p)] ?? ''
                        }}</span>
                        <Check
                            v-if="filters.priority === p"
                            class="size-3.5 text-foreground"
                        />
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        :disabled="filters.priority === null"
                        @select="navigate({ priority: null })"
                    >
                        <span class="text-muted-foreground">Clear</span>
                    </DropdownMenuItem>
                </DropdownMenuSubContent>
            </DropdownMenuSub>

            <!-- Assignee -->
            <DropdownMenuSub>
                <DropdownMenuSubTrigger
                    @pointerdown="loadMembers"
                    @pointerover="loadMembers"
                >
                    Assignee
                </DropdownMenuSubTrigger>
                <DropdownMenuSubContent class="max-h-72 w-60 overflow-y-auto">
                    <DropdownMenuItem @select="navigate({ assignee: 'me' })">
                        <span
                            class="flex size-4 items-center justify-center rounded-full bg-muted text-[10px]"
                            >M</span
                        >
                        <span class="flex-1">Just me</span>
                        <Check
                            v-if="filters.assignee === 'me'"
                            class="size-3.5 text-foreground"
                        />
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        @select="navigate({ assignee: 'unassigned' })"
                    >
                        <span
                            class="size-3.5 rounded-full border border-dashed border-border"
                        ></span>
                        <span class="flex-1">Unassigned</span>
                        <Check
                            v-if="filters.assignee === 'unassigned'"
                            class="size-3.5 text-foreground"
                        />
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <div
                        v-if="!membersLoaded"
                        class="px-2 py-1.5 text-xs text-muted-foreground"
                    >
                        {{
                            membersLoading ? 'Loading…' : 'Open to load members'
                        }}
                    </div>
                    <template v-else>
                        <DropdownMenuItem
                            v-for="m in members"
                            :key="m.id"
                            @select="navigate({ assignee: m.id })"
                        >
                            <Avatar
                                :name="m.name"
                                :email="m.email"
                                :size="16"
                            />
                            <span class="flex-1 truncate">{{ m.name }}</span>
                            <Check
                                v-if="filters.assignee === String(m.id)"
                                class="size-3.5 text-foreground"
                            />
                        </DropdownMenuItem>
                    </template>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        :disabled="!filters.assignee"
                        @select="navigate({ assignee: null })"
                    >
                        <span class="text-muted-foreground">Clear</span>
                    </DropdownMenuItem>
                </DropdownMenuSubContent>
            </DropdownMenuSub>

            <!-- Label -->
            <DropdownMenuSub v-if="labels.length">
                <DropdownMenuSubTrigger>Label</DropdownMenuSubTrigger>
                <DropdownMenuSubContent class="max-h-72 w-56 overflow-y-auto">
                    <DropdownMenuItem
                        v-for="l in labels"
                        :key="l.id"
                        @select.prevent="toggleLabel(l.id)"
                    >
                        <span
                            class="size-2.5 shrink-0 rounded-full"
                            :style="{ backgroundColor: l.color ?? '#94a3b8' }"
                        ></span>
                        <span class="flex-1 truncate">{{ l.name }}</span>
                        <Check
                            v-if="filters.labels.includes(l.id)"
                            class="size-3.5 text-foreground"
                        />
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        :disabled="!filters.labels.length"
                        @select="navigate({ labels: null })"
                    >
                        <span class="text-muted-foreground">Clear</span>
                    </DropdownMenuItem>
                </DropdownMenuSubContent>
            </DropdownMenuSub>

            <!-- Project -->
            <DropdownMenuSub v-if="projects.length">
                <DropdownMenuSubTrigger>Project</DropdownMenuSubTrigger>
                <DropdownMenuSubContent class="max-h-72 w-60 overflow-y-auto">
                    <DropdownMenuItem
                        v-for="p in projects"
                        :key="p.id"
                        @select="navigate({ project: p.id })"
                    >
                        <ProjectIcon
                            :icon="p.icon"
                            :color="p.color"
                            :size="14"
                        />
                        <span class="flex-1 truncate">{{ p.name }}</span>
                        <Check
                            v-if="filters.project === p.id"
                            class="size-3.5 text-foreground"
                        />
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        :disabled="filters.project === null"
                        @select="navigate({ project: null })"
                    >
                        <span class="text-muted-foreground">Clear</span>
                    </DropdownMenuItem>
                </DropdownMenuSubContent>
            </DropdownMenuSub>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
