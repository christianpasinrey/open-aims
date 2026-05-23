import { ref, watch } from 'vue';

export type UserSearchResult = {
    id: number;
    name: string;
    email: string;
    invited: boolean;
};

export function useUserSearch() {
    const query = ref('');
    const results = ref<UserSearchResult[]>([]);
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

        const timer = setTimeout(() => {
            loading.value = true;
            const seq = ++requestSeq;
            fetch('/workspace/users/search?q=' + encodeURIComponent(trimmed), {
                headers: { Accept: 'application/json' },
            })
                .then((r) => r.json())
                .then((json: { data: UserSearchResult[] }) => {
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

    return { query, results, loading };
}
