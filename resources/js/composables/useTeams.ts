import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

export type Team = {
    key: string;
    name: string;
    color: string | null;
    icon: string | null;
    issue_count: number;
    member_count: number;
};

export type CreateTeamPayload = {
    name: string;
    key?: string;
    color?: string;
    icon?: string;
};

export function useTeams() {
    const teams = ref<Team[]>([]);
    const loading = ref(false);

    async function refetch(): Promise<void> {
        loading.value = true;
        try {
            const res = await fetch('/workspace/teams?json=1', {
                headers: { Accept: 'application/json' },
            });
            const json = (await res.json()) as { data: Team[] };
            teams.value = json.data ?? [];
        } catch {
            teams.value = [];
        } finally {
            loading.value = false;
        }
    }

    function create(
        payload: CreateTeamPayload,
        onError?: (errors: Record<string, string>) => void,
    ): void {
        router.post('/teams', payload, {
            preserveScroll: true,
            onSuccess: () => {
                void refetch();
            },
            onError: (errors) => {
                onError?.(errors as Record<string, string>);
            },
        });
    }

    return { teams, loading, refetch, create };
}
