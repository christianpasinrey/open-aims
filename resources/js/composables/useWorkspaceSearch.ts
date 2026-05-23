import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

export type WorkspaceSearchResult = {
    name: string;
    slug: string;
    relationship: 'member' | 'open' | 'request' | 'pending';
};

export function useWorkspaceSearch() {
    const query = ref('');
    const results = ref<WorkspaceSearchResult[]>([]);
    const loading = ref(false);

    // Track latest request to discard out-of-order responses.
    let requestSeq = 0;

    watch(query, (val, _old, onCleanup) => {
        const trimmed = val.trim();

        if (!trimmed) {
            results.value = [];
            loading.value = false;
            return;
        }

        loading.value = true;

        const timer = setTimeout(() => {
            const seq = ++requestSeq;
            fetch('/workspaces/search?q=' + encodeURIComponent(trimmed), {
                headers: { Accept: 'application/json' },
            })
                .then((r) => r.json())
                .then((json: { data: WorkspaceSearchResult[] }) => {
                    if (seq !== requestSeq) return;
                    results.value = json.data ?? [];
                })
                .catch(() => {
                    if (seq !== requestSeq) return;
                    results.value = [];
                })
                .finally(() => {
                    if (seq === requestSeq) loading.value = false;
                });
        }, 300);

        // Cancel the pending timer when query changes again before it fires.
        onCleanup(() => clearTimeout(timer));
    });

    function join(slug: string): void {
        router.post('/workspaces/' + slug + '/join');
    }

    return { query, results, loading, join };
}
