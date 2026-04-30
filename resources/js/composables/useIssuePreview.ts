import { reactive } from 'vue';

export type IssuePreview = {
    identifier: string;
    title: string;
    priority: number;
    priority_label: string;
    state: { name: string; type: string; color: string } | null;
    assignee: { id: number; name: string; email: string } | null;
    project: {
        name: string;
        slug: string;
        color: string | null;
        icon: string | null;
    } | null;
    team: { key: string; name: string; color: string | null };
};

type Entry =
    | { status: 'idle' }
    | { status: 'loading' }
    | { status: 'ready'; data: IssuePreview }
    | { status: 'error'; message: string };

const cache = reactive<Record<string, Entry>>({});

export function useIssuePreview() {
    function get(identifier: string): Entry {
        return cache[identifier] ?? { status: 'idle' };
    }

    async function fetchPreview(identifier: string): Promise<void> {
        const current = cache[identifier];
        if (current?.status === 'loading' || current?.status === 'ready') {
            return;
        }

        cache[identifier] = { status: 'loading' };

        try {
            const res = await fetch(`/issues/${identifier}/preview`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) {
                cache[identifier] = {
                    status: 'error',
                    message: `HTTP ${res.status}`,
                };
                return;
            }
            const data = (await res.json()) as IssuePreview;
            cache[identifier] = { status: 'ready', data };
        } catch (e) {
            cache[identifier] = {
                status: 'error',
                message: e instanceof Error ? e.message : 'fetch failed',
            };
        }
    }

    function prime(data: IssuePreview): void {
        cache[data.identifier] = { status: 'ready', data };
    }

    return { get, fetchPreview, prime };
}
