<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Loader2, RotateCcw, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ProjectIcon from '@/components/repo/ProjectIcon.vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type TrashedProject = {
    id: number;
    name: string;
    slug: string;
    color: string | null;
    icon: string | null;
    state: string | null;
    deleted_at: string | null;
    issues_count: number;
    lead: { id: number; name: string; email: string } | null;
};

const props = defineProps<{
    projects: TrashedProject[];
}>();

const projects = computed<TrashedProject[]>(() => props.projects ?? []);

function fmtDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function relativeTime(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const diff = Math.max(0, Date.now() - new Date(iso).getTime());
    const m = Math.floor(diff / 60000);
    if (m < 60) {
        return `${m}m ago`;
    }
    const h = Math.floor(m / 60);
    if (h < 24) {
        return `${h}h ago`;
    }
    const days = Math.floor(h / 24);
    if (days < 30) {
        return `${days}d ago`;
    }
    return fmtDate(iso);
}

const restoringSlug = ref<string | null>(null);
function restore(p: TrashedProject) {
    restoringSlug.value = p.slug;
    router.post(
        `/projects/${p.slug}/restore`,
        {},
        {
            preserveScroll: false,
            onSuccess: () => {
                toast.success(`Restored "${p.name}"`);
            },
            onError: () => {
                toast.error('Could not restore project');
            },
            onFinish: () => {
                restoringSlug.value = null;
            },
        },
    );
}

const forceTarget = ref<TrashedProject | null>(null);
const forceSubmitting = ref<boolean>(false);
const forceDialogOpen = ref<boolean>(false);

function askForceDelete(p: TrashedProject) {
    forceTarget.value = p;
    forceDialogOpen.value = true;
}
function confirmForceDelete() {
    const p = forceTarget.value;
    if (!p) {
        return;
    }

    forceSubmitting.value = true;
    router.delete(`/projects/${p.slug}/force`, {
        preserveScroll: false,
        onSuccess: () => {
            toast.success(`Permanently deleted "${p.name}"`);
            forceDialogOpen.value = false;
            forceTarget.value = null;
        },
        onError: () => {
            toast.error('Could not delete project permanently');
        },
        onFinish: () => {
            forceSubmitting.value = false;
        },
    });
}
</script>

<template>
    <Head title="Trash" />

    <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden">
        <header
            class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4 py-2.5"
        >
            <div class="flex min-w-0 items-center gap-2 text-[12.5px]">
                <Trash2 class="size-4 text-muted-foreground" />
                <h1 class="font-medium text-foreground">Trash</h1>
                <span class="text-muted-foreground">·</span>
                <span class="text-muted-foreground"
                    >{{ projects.length }} project{{
                        projects.length === 1 ? '' : 's'
                    }}</span
                >
            </div>
        </header>

        <div class="flex-1 overflow-y-auto">
            <div
                v-if="!projects.length"
                class="flex h-full flex-col items-center justify-center gap-2 px-6 py-16 text-center"
            >
                <Trash2 class="size-8 text-muted-foreground/60" />
                <h2 class="text-base font-medium text-foreground">
                    Trash is empty
                </h2>
                <p class="max-w-sm text-sm text-muted-foreground">
                    Projects you delete are kept here. Restore to bring them
                    (and their issues) back.
                </p>
                <Link
                    href="/projects"
                    class="mt-3 rounded-md bg-foreground px-3 py-1.5 text-[13px] font-medium text-background hover:opacity-90"
                >
                    Back to projects
                </Link>
            </div>

            <ul v-else class="divide-y divide-border">
                <li
                    v-for="p in projects"
                    :key="p.id"
                    class="grid grid-cols-[auto_1fr_auto_auto_auto] items-center gap-3 px-4 py-2.5"
                >
                    <ProjectIcon
                        :icon="p.icon"
                        :color="p.color"
                        :size="20"
                        rounded="md"
                    />
                    <div class="min-w-0">
                        <div
                            class="truncate text-[13px] font-medium text-foreground"
                        >
                            {{ p.name }}
                        </div>
                        <div
                            class="truncate text-[11.5px] text-muted-foreground"
                        >
                            {{ p.issues_count }} issue{{
                                p.issues_count === 1 ? '' : 's'
                            }}
                            · deleted {{ relativeTime(p.deleted_at) }}
                        </div>
                    </div>
                    <span
                        class="hidden rounded-md border border-border bg-card px-2 py-px text-[11px] text-muted-foreground tabular-nums lg:inline-block"
                        :title="fmtDate(p.deleted_at)"
                    >
                        {{ fmtDate(p.deleted_at) }}
                    </span>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-2.5 py-1 text-[12.5px] text-foreground transition-colors hover:bg-accent disabled:opacity-50"
                        :disabled="restoringSlug === p.slug"
                        @click="restore(p)"
                    >
                        <Loader2
                            v-if="restoringSlug === p.slug"
                            class="size-3.5 animate-spin"
                        />
                        <RotateCcw v-else class="size-3.5" />
                        Restore
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md border border-rose-500/30 bg-rose-500/10 px-2.5 py-1 text-[12.5px] text-rose-400 transition-colors hover:bg-rose-500/20"
                        @click="askForceDelete(p)"
                    >
                        <Trash2 class="size-3.5" />
                        Delete forever
                    </button>
                </li>
            </ul>
        </div>

        <Dialog v-model:open="forceDialogOpen">
            <DialogContent class="sm:max-w-[460px]">
                <DialogHeader>
                    <DialogTitle>Delete forever?</DialogTitle>
                    <DialogDescription>
                        <strong class="text-foreground">{{
                            forceTarget?.name
                        }}</strong>
                        and its
                        {{ forceTarget?.issues_count ?? 0 }} issue{{
                            forceTarget?.issues_count === 1 ? '' : 's'
                        }}
                        will be permanently removed. This cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose
                        class="rounded-md px-3 py-1.5 text-[13px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    >
                        Cancel
                    </DialogClose>
                    <button
                        type="button"
                        :disabled="forceSubmitting"
                        class="inline-flex items-center gap-1.5 rounded-md bg-rose-500/90 px-3 py-1.5 text-[13px] font-medium text-white transition-opacity hover:opacity-90 disabled:opacity-50"
                        @click="confirmForceDelete"
                    >
                        <Loader2
                            v-if="forceSubmitting"
                            class="size-3.5 animate-spin"
                        />
                        <Trash2 v-else class="size-3.5" />
                        {{ forceSubmitting ? 'Deleting…' : 'Delete forever' }}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
