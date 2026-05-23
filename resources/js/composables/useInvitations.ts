import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

export type PendingInvitation = {
    id: number;
    role: string;
    expires_at: string | null;
    workspace: { name: string; slug: string };
    invited_by: { name: string } | null;
};

const items = ref<PendingInvitation[]>([]);
const loading = ref(false);

// Module-level interval handle so start/stop are paired correctly.
let pollHandle: ReturnType<typeof setInterval> | null = null;

async function refetch(): Promise<void> {
    loading.value = true;
    try {
        const res = await fetch('/invitations/pending', {
            headers: { Accept: 'application/json' },
        });
        const json = (await res.json()) as { data: PendingInvitation[] };
        items.value = json.data ?? [];
    } catch {
        // silently ignore network errors — keep stale data
    } finally {
        loading.value = false;
    }
}

function start(): void {
    void refetch();
    if (pollHandle === null) {
        pollHandle = setInterval(() => { void refetch(); }, 15_000);
    }
}

function stop(): void {
    if (pollHandle !== null) {
        clearInterval(pollHandle);
        pollHandle = null;
    }
}

function accept(id: number): void {
    router.post(
        '/invitations/' + id + '/accept',
        {},
        { preserveScroll: true, onSuccess: () => { void refetch(); } },
    );
}

function decline(id: number): void {
    router.post(
        '/invitations/' + id + '/decline',
        {},
        { preserveScroll: true, onSuccess: () => { void refetch(); } },
    );
}

export function useInvitations() {
    const count = computed(() => items.value.length);
    return { items, count, loading, refetch, start, stop, accept, decline };
}
