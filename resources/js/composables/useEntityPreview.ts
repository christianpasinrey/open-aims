import { reactive } from 'vue';

type Entry<T> =
    | { status: 'idle' }
    | { status: 'loading' }
    | { status: 'ready'; data: T }
    | { status: 'error'; message: string };

export function createPreviewStore<T>(buildUrl: (key: string | number) => string) {
    const cache = reactive<Record<string, Entry<T>>>({});

    function get(key: string | number): Entry<T> {
        return cache[String(key)] ?? { status: 'idle' };
    }

    async function fetchPreview(key: string | number): Promise<void> {
        const k = String(key);
        const current = cache[k];
        if (current?.status === 'loading' || current?.status === 'ready') {
            return;
        }
        cache[k] = { status: 'loading' };

        try {
            const res = await fetch(buildUrl(key), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) {
                cache[k] = { status: 'error', message: `HTTP ${res.status}` };
                return;
            }
            cache[k] = { status: 'ready', data: (await res.json()) as T };
        } catch (e) {
            cache[k] = {
                status: 'error',
                message: e instanceof Error ? e.message : 'fetch failed',
            };
        }
    }

    return { get, fetchPreview };
}

export type LabelPreview = {
    id: number;
    name: string;
    color: string | null;
    description: string | null;
    team: { id: number; name: string; key: string } | null;
    issues: { total: number };
};

export type ProjectPreview = {
    name: string;
    slug: string;
    description: string | null;
    state: string;
    color: string | null;
    icon: string | null;
    start_date: string | null;
    target_date: string | null;
    completed_at: string | null;
    lead: { id: number; name: string; email: string } | null;
    issues: { total: number; completed: number; progress: number };
};

const labelStore = createPreviewStore<LabelPreview>(
    (id) => `/labels/${id}/preview`,
);
const projectStore = createPreviewStore<ProjectPreview>(
    (slug) => `/projects/${slug}/preview`,
);

export const useLabelPreview = () => labelStore;
export const useProjectPreview = () => projectStore;
