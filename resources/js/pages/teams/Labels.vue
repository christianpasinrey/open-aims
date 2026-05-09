<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Loader2, Pencil, Plus, Tag, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import LabelBadge from '@/components/repo/LabelBadge.vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label as UILabel } from '@/components/ui/label';

type Label = {
    id: number;
    name: string;
    color: string | null;
    description: string | null;
    issues_count: number;
};

const props = defineProps<{
    team: { id: number; name: string; key: string; color: string | null };
    labels: Label[];
}>();

const search = ref<string>('');

const filtered = computed<Label[]>(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) {
        return props.labels;
    }
    return props.labels.filter((l) => l.name.toLowerCase().includes(q));
});

// ─── Create ─────────────────────────────────────────────────────────────
const createOpen = ref<boolean>(false);
const createForm = ref<{ name: string; color: string; description: string }>({
    name: '',
    color: '#6366f1',
    description: '',
});
const createSubmitting = ref<boolean>(false);
const createError = ref<string | null>(null);

function openCreate() {
    createForm.value = { name: '', color: '#6366f1', description: '' };
    createError.value = null;
    createOpen.value = true;
}
function submitCreate() {
    if (!createForm.value.name.trim()) {
        createError.value = 'Name is required.';
        return;
    }
    createSubmitting.value = true;
    router.post(
        `/teams/${props.team.key}/labels`,
        {
            name: createForm.value.name.trim(),
            color: createForm.value.color,
            description: createForm.value.description.trim() || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                createOpen.value = false;
                toast.success(`Created "${createForm.value.name.trim()}"`);
            },
            onError: (errors) => {
                createError.value =
                    (Object.values(errors)[0] as string | undefined) ??
                    'Could not create label.';
            },
            onFinish: () => {
                createSubmitting.value = false;
            },
        },
    );
}

// ─── Edit ───────────────────────────────────────────────────────────────
const editOpen = ref<boolean>(false);
const editTarget = ref<Label | null>(null);
const editForm = ref<{ name: string; color: string; description: string }>({
    name: '',
    color: '#6366f1',
    description: '',
});
const editSubmitting = ref<boolean>(false);
const editError = ref<string | null>(null);

function openEdit(label: Label) {
    editTarget.value = label;
    editForm.value = {
        name: label.name,
        color: label.color ?? '#6366f1',
        description: label.description ?? '',
    };
    editError.value = null;
    editOpen.value = true;
}
function submitEdit() {
    const target = editTarget.value;
    if (!target) {
        return;
    }
    if (!editForm.value.name.trim()) {
        editError.value = 'Name is required.';
        return;
    }
    editSubmitting.value = true;
    router.patch(
        `/labels/${target.id}`,
        {
            name: editForm.value.name.trim(),
            color: editForm.value.color,
            description: editForm.value.description.trim() || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                editOpen.value = false;
                toast.success(`Updated "${editForm.value.name.trim()}"`);
            },
            onError: (errors) => {
                editError.value =
                    (Object.values(errors)[0] as string | undefined) ??
                    'Could not update label.';
            },
            onFinish: () => {
                editSubmitting.value = false;
            },
        },
    );
}

// ─── Delete ─────────────────────────────────────────────────────────────
const deleteOpen = ref<boolean>(false);
const deleteTarget = ref<Label | null>(null);
const deleteSubmitting = ref<boolean>(false);

function askDelete(label: Label) {
    deleteTarget.value = label;
    deleteOpen.value = true;
}
function confirmDelete() {
    const target = deleteTarget.value;
    if (!target) {
        return;
    }
    deleteSubmitting.value = true;
    router.delete(`/labels/${target.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteOpen.value = false;
            toast.success(`Deleted "${target.name}"`);
        },
        onError: () => {
            toast.error('Could not delete label');
        },
        onFinish: () => {
            deleteSubmitting.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`${team.name} · Labels`" />

    <div class="flex h-full flex-1 flex-col overflow-hidden">
        <header
            class="flex shrink-0 items-center gap-3 border-b border-border px-5 py-3"
        >
            <span
                class="flex size-6 items-center justify-center rounded-md text-[10px] font-semibold text-white uppercase"
                :style="{ backgroundColor: team.color || '#6366f1' }"
            >
                {{ team.key.charAt(0) }}
            </span>
            <h1 class="text-[13px] font-medium">{{ team.name }}</h1>
            <span class="text-[12px] text-muted-foreground uppercase"
                >Labels</span
            >
            <Link
                :href="`/teams/${team.key}/settings`"
                class="ml-auto rounded-md px-2.5 py-1 text-[12.5px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
            >
                Settings
            </Link>
            <Link
                :href="`/teams/${team.key}/members`"
                class="rounded-md px-2.5 py-1 text-[12.5px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
            >
                Members
            </Link>
        </header>

        <div class="flex-1 overflow-y-auto px-6 py-6">
            <div class="mx-auto max-w-3xl space-y-4">
                <div class="flex items-center justify-between gap-2">
                    <Input
                        v-model="search"
                        placeholder="Search labels…"
                        class="h-9 max-w-sm text-[13px]"
                    />
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md bg-foreground px-3 py-1.5 text-[13px] font-medium text-background hover:opacity-90"
                        @click="openCreate"
                    >
                        <Plus class="size-3.5" />
                        New label
                    </button>
                </div>

                <div
                    v-if="!filtered.length"
                    class="flex flex-col items-center justify-center gap-2 rounded-md border border-dashed border-border px-6 py-12 text-center"
                >
                    <Tag class="size-8 text-muted-foreground/60" />
                    <p class="text-[13px] text-muted-foreground">
                        {{
                            search.trim()
                                ? 'No labels match your search.'
                                : 'No labels yet — create your first one.'
                        }}
                    </p>
                </div>

                <ul
                    v-else
                    class="divide-y divide-border rounded-md border border-border"
                >
                    <li
                        v-for="l in filtered"
                        :key="l.id"
                        class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-3 px-3 py-2"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <LabelBadge :name="l.name" :color="l.color" />
                            <span
                                v-if="l.description"
                                class="truncate text-[12.5px] text-muted-foreground"
                            >
                                {{ l.description }}
                            </span>
                        </div>
                        <span
                            class="text-[11.5px] text-muted-foreground tabular-nums"
                        >
                            {{ l.issues_count }} issue{{
                                l.issues_count === 1 ? '' : 's'
                            }}
                        </span>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-md border border-border bg-card px-2 py-1 text-[12px] text-foreground hover:bg-accent"
                            @click="openEdit(l)"
                        >
                            <Pencil class="size-3" />
                            Edit
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-md border border-rose-500/30 bg-rose-500/10 px-2 py-1 text-[12px] text-rose-400 hover:bg-rose-500/20"
                            @click="askDelete(l)"
                        >
                            <Trash2 class="size-3" />
                            Delete
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Create dialog -->
        <Dialog v-model:open="createOpen">
            <DialogContent class="sm:max-w-[460px]">
                <DialogHeader>
                    <DialogTitle>New label</DialogTitle>
                    <DialogDescription
                        >Labels in {{ team.name }} apply to issues in this
                        team.</DialogDescription
                    >
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitCreate">
                    <div class="grid gap-2">
                        <UILabel for="cl-name">Name</UILabel>
                        <Input
                            id="cl-name"
                            v-model="createForm.name"
                            maxlength="80"
                            placeholder="e.g. bug"
                            autofocus
                        />
                    </div>
                    <div class="grid gap-2">
                        <UILabel for="cl-color">Color</UILabel>
                        <div class="flex items-center gap-3">
                            <input
                                id="cl-color"
                                v-model="createForm.color"
                                type="color"
                                class="h-9 w-12 cursor-pointer rounded-md border border-input bg-transparent"
                            />
                            <Input
                                :model-value="createForm.color"
                                @update:model-value="
                                    createForm.color = String($event)
                                "
                                maxlength="9"
                                class="font-mono text-[12.5px]"
                            />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <UILabel for="cl-desc">Description</UILabel>
                        <textarea
                            id="cl-desc"
                            v-model="createForm.description"
                            rows="2"
                            maxlength="255"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-[13px] outline-none focus-visible:border-ring"
                        />
                    </div>
                    <p v-if="createError" class="text-[12px] text-rose-400">
                        {{ createError }}
                    </p>
                    <DialogFooter>
                        <DialogClose
                            class="rounded-md px-3 py-1.5 text-[13px] text-muted-foreground hover:bg-accent hover:text-foreground"
                        >
                            Cancel
                        </DialogClose>
                        <button
                            type="submit"
                            :disabled="createSubmitting"
                            class="inline-flex items-center gap-1.5 rounded-md bg-foreground px-3 py-1.5 text-[13px] font-medium text-background hover:opacity-90 disabled:opacity-50"
                        >
                            <Loader2
                                v-if="createSubmitting"
                                class="size-3.5 animate-spin"
                            />
                            {{
                                createSubmitting ? 'Creating…' : 'Create label'
                            }}
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit dialog -->
        <Dialog v-model:open="editOpen">
            <DialogContent class="sm:max-w-[460px]">
                <DialogHeader>
                    <DialogTitle>Edit label</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitEdit">
                    <div class="grid gap-2">
                        <UILabel for="el-name">Name</UILabel>
                        <Input
                            id="el-name"
                            v-model="editForm.name"
                            maxlength="80"
                        />
                    </div>
                    <div class="grid gap-2">
                        <UILabel for="el-color">Color</UILabel>
                        <div class="flex items-center gap-3">
                            <input
                                id="el-color"
                                v-model="editForm.color"
                                type="color"
                                class="h-9 w-12 cursor-pointer rounded-md border border-input bg-transparent"
                            />
                            <Input
                                :model-value="editForm.color"
                                @update:model-value="
                                    editForm.color = String($event)
                                "
                                maxlength="9"
                                class="font-mono text-[12.5px]"
                            />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <UILabel for="el-desc">Description</UILabel>
                        <textarea
                            id="el-desc"
                            v-model="editForm.description"
                            rows="2"
                            maxlength="255"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-[13px] outline-none focus-visible:border-ring"
                        />
                    </div>
                    <p v-if="editError" class="text-[12px] text-rose-400">
                        {{ editError }}
                    </p>
                    <DialogFooter>
                        <DialogClose
                            class="rounded-md px-3 py-1.5 text-[13px] text-muted-foreground hover:bg-accent hover:text-foreground"
                        >
                            Cancel
                        </DialogClose>
                        <button
                            type="submit"
                            :disabled="editSubmitting"
                            class="inline-flex items-center gap-1.5 rounded-md bg-foreground px-3 py-1.5 text-[13px] font-medium text-background hover:opacity-90 disabled:opacity-50"
                        >
                            <Loader2
                                v-if="editSubmitting"
                                class="size-3.5 animate-spin"
                            />
                            {{ editSubmitting ? 'Saving…' : 'Save' }}
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete confirm -->
        <Dialog v-model:open="deleteOpen">
            <DialogContent class="sm:max-w-[440px]">
                <DialogHeader>
                    <DialogTitle>Delete label?</DialogTitle>
                    <DialogDescription>
                        <strong class="text-foreground">{{
                            deleteTarget?.name
                        }}</strong>
                        will be removed from
                        {{ deleteTarget?.issues_count ?? 0 }} issue{{
                            deleteTarget?.issues_count === 1 ? '' : 's'
                        }}. This cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose
                        class="rounded-md px-3 py-1.5 text-[13px] text-muted-foreground hover:bg-accent hover:text-foreground"
                    >
                        Cancel
                    </DialogClose>
                    <button
                        type="button"
                        :disabled="deleteSubmitting"
                        class="inline-flex items-center gap-1.5 rounded-md bg-rose-500/90 px-3 py-1.5 text-[13px] font-medium text-white hover:opacity-90 disabled:opacity-50"
                        @click="confirmDelete"
                    >
                        <Loader2
                            v-if="deleteSubmitting"
                            class="size-3.5 animate-spin"
                        />
                        <Trash2 v-else class="size-3.5" />
                        {{ deleteSubmitting ? 'Deleting…' : 'Delete' }}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
