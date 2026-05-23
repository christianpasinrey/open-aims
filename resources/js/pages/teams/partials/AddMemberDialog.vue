<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Loader2, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Avatar from '@/components/repo/Avatar.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ExistingMember = {
    id: number;
    role: string | null;
    user: { id: number; name: string; email: string };
};

type Candidate = { id: number; name: string; email: string };

const props = defineProps<{
    open: boolean;
    teamKey: string;
    members: ExistingMember[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const loading = ref(false);
const candidates = ref<Candidate[]>([]);
const query = ref('');
const roles = ref<Record<number, string>>({});
const submitting = ref<number | null>(null);

const available = computed<Candidate[]>(() => {
    const taken = new Set(
        (props.members ?? [])
            .map((m) => m.user?.id)
            .filter((id): id is number => typeof id === 'number'),
    );

    return candidates.value.filter((c) => !taken.has(c.id));
});

const filtered = computed<Candidate[]>(() => {
    const q = query.value.trim().toLowerCase();

    if (!q) {
        return available.value;
    }

    return available.value.filter(
        (c) =>
            c.name.toLowerCase().includes(q) ||
            c.email.toLowerCase().includes(q),
    );
});

async function loadCandidates() {
    loading.value = true;

    try {
        const res = await fetch('/workspace/members?json=1', {
            headers: { Accept: 'application/json' },
        });
        const json = await res.json();
        candidates.value = Array.isArray(json.data) ? json.data : [];
        for (const c of candidates.value) {
            if (!roles.value[c.id]) {
                roles.value[c.id] = 'member';
            }
        }
    } catch {
        candidates.value = [];
    } finally {
        loading.value = false;
    }
}

function onOpenChange(value: boolean) {
    emit('update:open', value);

    if (value) {
        query.value = '';
        loadCandidates();
    }
}

function add(candidate: Candidate) {
    if (submitting.value !== null) {
        return;
    }

    const role = roles.value[candidate.id] ?? 'member';
    submitting.value = candidate.id;

    router.post(
        `/teams/${props.teamKey}/members`,
        { user_id: candidate.id, role },
        {
            preserveScroll: true,
            onSuccess: () => {
                // The candidate is now a team member; available recomputes once
                // the page props refresh, so just close the dialog.
                emit('update:open', false);
            },
            onFinish: () => {
                submitting.value = null;
            },
        },
    );
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Añadir miembro</DialogTitle>
                <DialogDescription>
                    Elige un miembro del workspace y asígnale un rol en el
                    equipo.
                </DialogDescription>
            </DialogHeader>

            <div
                class="flex items-center gap-2 rounded-md border border-border px-2.5 py-1.5"
            >
                <Search class="size-3.5 text-muted-foreground" />
                <input
                    v-model="query"
                    type="text"
                    placeholder="Buscar miembros…"
                    class="flex-1 bg-transparent text-[13px] outline-none placeholder:text-muted-foreground"
                />
            </div>

            <div
                v-if="loading"
                class="flex items-center justify-center gap-2 py-8 text-[13px] text-muted-foreground"
            >
                <Loader2 class="size-4 animate-spin" />
                Cargando…
            </div>

            <ul
                v-else-if="filtered.length"
                class="max-h-72 divide-y divide-border overflow-y-auto"
            >
                <li
                    v-for="c in filtered"
                    :key="c.id"
                    class="flex items-center gap-3 py-2.5"
                >
                    <Avatar :name="c.name" :email="c.email" :size="28" />
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-[13px] font-medium">
                            {{ c.name }}
                        </div>
                        <div class="truncate text-[12px] text-muted-foreground">
                            {{ c.email }}
                        </div>
                    </div>
                    <select
                        v-model="roles[c.id]"
                        class="rounded-md border border-border bg-card px-2 py-1 text-[12px] outline-none"
                    >
                        <option value="member">Miembro</option>
                        <option value="lead">Lead</option>
                    </select>
                    <Button
                        size="sm"
                        :disabled="submitting === c.id"
                        @click="add(c)"
                    >
                        <Loader2
                            v-if="submitting === c.id"
                            class="size-3.5 animate-spin"
                        />
                        <span v-else>Añadir</span>
                    </Button>
                </li>
            </ul>

            <p
                v-else
                class="py-8 text-center text-[13px] text-muted-foreground"
            >
                No hay miembros del workspace disponibles para añadir.
            </p>
        </DialogContent>
    </Dialog>
</template>
