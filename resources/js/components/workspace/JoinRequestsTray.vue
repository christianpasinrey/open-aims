<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

type JoinRequest = {
    id: number;
    user: { id: number; name: string; email: string };
    created_at: string;
};

const props = defineProps<{
    currentRole: string | null;
}>();

const rows = ref<JoinRequest[]>([]);
const loading = ref(false);
const processingId = ref<number | null>(null);

function canManage(): boolean {
    return props.currentRole === 'owner' || props.currentRole === 'admin';
}

async function refetch(): Promise<void> {
    if (!canManage()) return;
    loading.value = true;
    try {
        const res = await fetch('/workspace/requests', {
            headers: { Accept: 'application/json' },
        });
        const json = (await res.json()) as { data: JoinRequest[] };
        rows.value = json.data ?? [];
    } catch {
        rows.value = [];
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    if (typeof window === 'undefined') return;
    if (!canManage()) return;
    void refetch();
});

function approve(id: number): void {
    processingId.value = id;
    router.post(
        '/workspace/requests/' + id + '/approve',
        {},
        {
            preserveScroll: true,
            onSuccess: () => { void refetch(); },
            onFinish: () => { processingId.value = null; },
        },
    );
}

function reject(id: number): void {
    processingId.value = id;
    router.post(
        '/workspace/requests/' + id + '/reject',
        {},
        {
            preserveScroll: true,
            onSuccess: () => { void refetch(); },
            onFinish: () => { processingId.value = null; },
        },
    );
}
</script>

<template>
    <div v-if="canManage() && (loading || rows.length)" class="mb-4 rounded-md border border-border">
        <div class="flex items-center gap-2 border-b border-border px-4 py-2.5">
            <span class="text-[12px] font-medium uppercase tracking-wide text-muted-foreground">
                Solicitudes de acceso
            </span>
            <span
                v-if="rows.length"
                class="rounded-full bg-brand/15 px-1.5 py-0.5 text-[11px] font-semibold text-brand"
            >
                {{ rows.length }}
            </span>
            <Loader2 v-if="loading" class="ml-auto size-3.5 animate-spin text-muted-foreground" />
        </div>

        <ul v-if="rows.length" class="divide-y divide-border">
            <li
                v-for="req in rows"
                :key="req.id"
                class="flex items-center justify-between gap-3 px-4 py-3"
            >
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[13px] font-medium text-foreground">
                        {{ req.user.name }}
                    </p>
                    <p class="truncate text-[12px] text-muted-foreground">
                        {{ req.user.email }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :disabled="processingId === req.id"
                        class="inline-flex h-7 items-center gap-1 rounded-md bg-brand px-2.5 text-[12px] font-medium text-brand-foreground transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="approve(req.id)"
                    >
                        <Loader2 v-if="processingId === req.id" class="size-3 animate-spin" />
                        Aprobar
                    </button>
                    <button
                        type="button"
                        :disabled="processingId === req.id"
                        class="inline-flex h-7 items-center rounded-md px-2.5 text-[12px] font-medium text-destructive ring-1 ring-destructive/40 transition-colors hover:bg-destructive/10 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="reject(req.id)"
                    >
                        Rechazar
                    </button>
                </div>
            </li>
        </ul>
    </div>
</template>
