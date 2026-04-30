<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Bell,
    CalendarRange,
    CheckCircle2,
    ChevronRight,
    LayoutGrid,
    Loader2,
    Plus,
    SlidersHorizontal,
    Star,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFavourites } from '@/composables/useFavourites';

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

type Team = { id: number; name: string; key: string; color: string | null };

type ViewKey = 'all' | 'current' | 'upcoming' | 'completed';
type SortKey = 'date_desc' | 'number_desc';

const props = defineProps<{
    team: Team | null;
    cycles: Cycle[];
    filters?: {
        team: string | null;
        view: ViewKey;
        sort: SortKey;
    };
}>();

const view = computed<ViewKey>(() => props.filters?.view ?? 'all');
const sort = computed<SortKey>(() => props.filters?.sort ?? 'date_desc');
const teamKey = computed<string | null>(() => props.team?.key ?? null);

function buildHref(
    overrides: Partial<{ view: ViewKey; sort: SortKey }>,
): string {
    const params = new URLSearchParams();

    if (teamKey.value) {
        params.set('team', teamKey.value);
    }

    const v = overrides.view ?? view.value;
    const s = overrides.sort ?? sort.value;

    if (v !== 'all') {
        params.set('view', v);
    }

    if (s !== 'date_desc') {
        params.set('sort', s);
    }

    const q = params.toString();

    return q ? `/cycles?${q}` : '/cycles';
}

const tabs = computed(() =>
    [
        { key: 'all' as const, label: 'All cycles' },
        { key: 'current' as const, label: 'Current' },
        { key: 'upcoming' as const, label: 'Upcoming' },
        { key: 'completed' as const, label: 'Completed' },
    ].map((t) => ({ ...t, href: buildHref({ view: t.key }) })),
);

// "Show completed" filter is just sugar over the All view; when off and the
// view is 'all', we hide completed cycles client-side.
const showCompleted = ref(true);

const visibleCycles = computed<Cycle[]>(() => {
    if (showCompleted.value) {
        return props.cycles;
    }

    if (view.value === 'completed') {
        return props.cycles;
    }

    return (props.cycles ?? []).filter((c) => !c.completed_at);
});

function applySort(s: SortKey) {
    router.visit(buildHref({ sort: s }), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

// ─── Favourites (server-side) ────────────────────────────────────────────
const { isFavourited, toggle: toggleFav } = useFavourites();

const teamCyclesHref = computed<string>(() =>
    teamKey.value ? `/cycles?team=${teamKey.value}` : '/cycles',
);
const teamViewFavorited = computed<boolean>(() =>
    isFavourited('team_view', teamCyclesHref.value),
);

function toggleTeamViewFavorite() {
    toggleFav({
        kind: 'team_view',
        href: teamCyclesHref.value,
        label: props.team
            ? `${props.team.name} · Cycles`
            : 'Cycles',
        icon: 'CalendarRange',
        color: props.team?.color ?? null,
    });
}

function isCycleFav(cycle: Cycle): boolean {
    return isFavourited('cycle', cycle.id);
}

function toggleCycleFavorite(cycle: Cycle) {
    const href = teamKey.value
        ? `/cycles/${cycle.number}?team=${teamKey.value}`
        : `/cycles/${cycle.number}`;
    toggleFav({
        kind: 'cycle',
        href,
        label: cycle.name || `Cycle ${cycle.number}`,
        icon: 'CalendarRange',
        color: props.team?.color ?? null,
        target_type: 'App\\Modules\\Cycles\\Models\\Cycle',
        target_id: cycle.id,
    });
}

// ─── New Cycle dialog ────────────────────────────────────────────────────
const dialogOpen = ref(false);
const submitting = ref(false);
const formError = ref<string | null>(null);

const nextNumberSuggestion = computed<number>(() => {
    if (!(props.cycles ?? []).length) {
        return 1;
    }

    return Math.max(...(props.cycles ?? []).map((c) => c.number)) + 1;
});

const form = ref({
    name: '',
    number: 0,
    starts_at: '',
    ends_at: '',
    description: '',
});

function openDialog() {
    formError.value = null;
    form.value = {
        name: '',
        number: nextNumberSuggestion.value,
        starts_at: todayIso(),
        ends_at: addDaysIso(todayIso(), 14),
        description: '',
    };
    dialogOpen.value = true;
}
function closeDialog() {
    dialogOpen.value = false;
}

function todayIso(): string {
    const d = new Date();

    return d.toISOString().slice(0, 10);
}
function addDaysIso(iso: string, days: number): string {
    const d = new Date(iso);
    d.setDate(d.getDate() + days);

    return d.toISOString().slice(0, 10);
}

function submitNewCycle() {
    if (!teamKey.value) {
        formError.value = 'Pick a team first.';

        return;
    }

    if (!form.value.starts_at || !form.value.ends_at) {
        formError.value = 'Both start and end dates are required.';

        return;
    }

    if (new Date(form.value.starts_at) > new Date(form.value.ends_at)) {
        formError.value = 'End date cannot be before start date.';

        return;
    }

    submitting.value = true;
    router.post(
        `/cycles?team=${encodeURIComponent(teamKey.value)}`,
        {
            name: form.value.name || null,
            number: form.value.number || null,
            starts_at: form.value.starts_at,
            ends_at: form.value.ends_at,
            description: form.value.description || null,
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                formError.value =
                    Object.values(errors)[0] ?? 'Could not create cycle.';
            },
            onFinish: () => {
                submitting.value = false;
            },
            onSuccess: () => {
                dialogOpen.value = false;
                toast.success('Cycle created');
            },
        },
    );
}

function fmtDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function cycleHref(c: Cycle): string {
    if (!teamKey.value) {
        return `/cycles/${c.number}`;
    }

    return `/cycles/${c.number}?team=${encodeURIComponent(teamKey.value)}`;
}
</script>

<template>
    <Head :title="team ? `${team.name} · Cycles` : 'Cycles'" />

    <div class="flex h-full flex-1 flex-col overflow-hidden">
        <!-- Top bar -->
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
                <CalendarRange v-else class="size-4 text-muted-foreground" />
                <h1 class="text-[13px] font-medium text-foreground">
                    {{ team ? `${team.name} · Cycles` : 'Cycles' }}
                </h1>
                <button
                    type="button"
                    :class="[
                        'transition-colors',
                        teamViewFavorited
                            ? 'text-amber-400 hover:text-amber-500'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                    :aria-label="
                        teamViewFavorited ? 'Unfavourite' : 'Favourite'
                    "
                    @click="toggleTeamViewFavorite"
                >
                    <Star
                        class="size-3.5"
                        :fill="teamViewFavorited ? 'currentColor' : 'none'"
                    />
                </button>
            </div>
            <div class="flex items-center gap-1 text-muted-foreground">
                <button
                    type="button"
                    class="rounded-md p-1.5 transition-colors hover:bg-accent hover:text-foreground"
                    aria-label="New cycle"
                    title="New cycle"
                    :disabled="!team"
                    @click="openDialog"
                >
                    <Plus class="size-3.5" />
                </button>
                <Link
                    href="/inbox"
                    class="rounded-md p-1.5 transition-colors hover:bg-accent hover:text-foreground"
                    aria-label="Inbox"
                    title="Inbox"
                >
                    <Bell class="size-3.5" />
                </Link>
            </div>
        </header>

        <!-- Sub-tabs + display dropdowns -->
        <div
            class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4"
        >
            <nav class="flex items-center gap-1 py-2 text-[12.5px]">
                <Link
                    v-for="tab in tabs"
                    :key="tab.key"
                    :href="tab.href"
                    :class="[
                        'rounded-md px-2 py-1 transition-colors',
                        view === tab.key
                            ? 'bg-accent text-foreground'
                            : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                    ]"
                >
                    {{ tab.label }}
                </Link>
            </nav>
            <div class="flex items-center gap-1 text-muted-foreground">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="rounded-md p-1.5 transition-colors hover:bg-accent hover:text-foreground"
                            aria-label="Filter"
                            title="Filter"
                        >
                            <SlidersHorizontal class="size-3.5" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <DropdownMenuLabel>Filter</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuCheckboxItem
                            :model-value="showCompleted"
                            @update:model-value="showCompleted = !!$event"
                        >
                            Show completed
                        </DropdownMenuCheckboxItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="rounded-md p-1.5 transition-colors hover:bg-accent hover:text-foreground"
                            aria-label="Display options"
                            title="Display"
                        >
                            <LayoutGrid class="size-3.5" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <DropdownMenuLabel>Sort by</DropdownMenuLabel>
                        <DropdownMenuRadioGroup
                            :model-value="sort"
                            @update:model-value="(v) => applySort(v as SortKey)"
                        >
                            <DropdownMenuRadioItem value="date_desc">
                                Date (newest first)
                            </DropdownMenuRadioItem>
                            <DropdownMenuRadioItem value="number_desc">
                                Number (highest first)
                            </DropdownMenuRadioItem>
                        </DropdownMenuRadioGroup>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <!-- Empty state -->
        <div
            v-if="!visibleCycles.length"
            class="flex flex-1 items-center justify-center px-6 py-12 text-center"
        >
            <div class="max-w-sm">
                <div
                    class="mx-auto flex size-10 items-center justify-center rounded-md bg-muted text-muted-foreground"
                >
                    <CalendarRange class="size-5" />
                </div>
                <h2 class="mt-4 text-base font-medium text-foreground">
                    {{
                        !team
                            ? 'No team selected'
                            : view === 'all'
                              ? 'No cycles yet'
                              : `No ${view} cycles`
                    }}
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{
                        !team
                            ? 'Pick a team from the sidebar to view its cycles.'
                            : view === 'all'
                              ? 'Cycles are time-boxed iterations. Create your first one to start planning work.'
                              : view === 'current'
                                ? 'No cycle is running for this team right now.'
                                : view === 'upcoming'
                                  ? 'Plan ahead by scheduling an upcoming cycle.'
                                  : 'Completed cycles will appear here once they wrap up.'
                    }}
                </p>
                <button
                    v-if="team && view !== 'completed'"
                    type="button"
                    class="mt-5 inline-flex items-center gap-1.5 rounded-md bg-foreground px-3 py-1.5 text-[13px] font-medium text-background transition-opacity hover:opacity-90"
                    @click="openDialog"
                >
                    <Plus class="size-3.5" />
                    Create your first cycle
                </button>
            </div>
        </div>

        <!-- Rows -->
        <ul v-else class="flex-1 divide-y divide-border overflow-y-auto">
            <li
                v-for="cycle in visibleCycles"
                :key="cycle.id"
                class="group flex h-9 items-center gap-3 px-4 hover:bg-accent/40"
            >
                <Link
                    :href="cycleHref(cycle)"
                    class="flex min-w-0 flex-1 items-center gap-3"
                >
                    <span
                        :class="[
                            'flex size-6 shrink-0 items-center justify-center rounded-md text-[11px] font-semibold tabular-nums',
                            cycle.is_current
                                ? 'bg-indigo-500/15 text-indigo-500 ring-1 ring-indigo-500/30 ring-inset'
                                : cycle.completed_at
                                  ? 'bg-muted text-muted-foreground'
                                  : 'bg-muted text-foreground',
                        ]"
                    >
                        {{ cycle.number }}
                    </span>
                    <div class="flex min-w-0 items-center gap-2">
                        <span class="truncate text-[13px] text-foreground">
                            {{ cycle.name }}
                        </span>
                        <span
                            v-if="cycle.is_current"
                            class="rounded-full border border-indigo-500/40 bg-indigo-500/10 px-1.5 py-px text-[10px] font-medium text-indigo-500"
                            >Current</span
                        >
                        <CheckCircle2
                            v-if="cycle.completed_at"
                            class="size-3 text-emerald-500"
                            aria-label="Completed"
                        />
                    </div>
                </Link>
                <span
                    class="hidden text-[11.5px] text-muted-foreground tabular-nums sm:inline"
                >
                    {{ fmtDate(cycle.starts_at) }} →
                    {{ fmtDate(cycle.ends_at) }}
                </span>
                <button
                    type="button"
                    :class="[
                        'rounded p-0.5 transition-colors',
                        isCycleFav(cycle)
                            ? 'text-amber-400 hover:text-amber-500'
                            : 'text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-accent',
                    ]"
                    :aria-label="
                        isCycleFav(cycle)
                            ? 'Unfavourite cycle'
                            : 'Favourite cycle'
                    "
                    @click.stop.prevent="toggleCycleFavorite(cycle)"
                >
                    <Star
                        class="size-3.5"
                        :fill="isCycleFav(cycle) ? 'currentColor' : 'none'"
                    />
                </button>
                <Link
                    :href="cycleHref(cycle)"
                    class="text-muted-foreground"
                    aria-label="Open cycle"
                >
                    <ChevronRight class="size-3.5" />
                </Link>
            </li>
        </ul>

        <!-- New Cycle dialog -->
        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-[440px]">
                <DialogHeader>
                    <DialogTitle>New cycle</DialogTitle>
                    <DialogDescription>
                        Cycles bracket a span of work for the team.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitNewCycle">
                    <div class="grid grid-cols-[1fr_88px] gap-3">
                        <div class="grid gap-1.5">
                            <Label for="cycle-name">Name</Label>
                            <Input
                                id="cycle-name"
                                v-model="form.name"
                                :placeholder="`Cycle ${form.number || nextNumberSuggestion}`"
                                autocomplete="off"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="cycle-number">Number</Label>
                            <Input
                                id="cycle-number"
                                v-model.number="form.number"
                                type="number"
                                min="1"
                                step="1"
                                class="tabular-nums"
                            />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label for="cycle-starts">Starts</Label>
                            <Input
                                id="cycle-starts"
                                v-model="form.starts_at"
                                type="date"
                                required
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="cycle-ends">Ends</Label>
                            <Input
                                id="cycle-ends"
                                v-model="form.ends_at"
                                type="date"
                                required
                            />
                        </div>
                    </div>
                    <p v-if="formError" class="text-[12px] text-red-500">
                        {{ formError }}
                    </p>
                    <DialogFooter>
                        <DialogClose as-child>
                            <Button
                                type="button"
                                variant="secondary"
                                @click="closeDialog"
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button type="submit" :disabled="submitting">
                            <Loader2
                                v-if="submitting"
                                class="size-3.5 animate-spin"
                                aria-hidden="true"
                            />
                            {{ submitting ? 'Creating…' : 'Create cycle' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
