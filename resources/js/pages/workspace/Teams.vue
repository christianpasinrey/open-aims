<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Loader2, Users, Settings } from 'lucide-vue-next';
import { defineAsyncComponent, onMounted } from 'vue';
import { useTeams } from '@/composables/useTeams';

const CreateTeamForm = defineAsyncComponent(
    () => import('@/components/workspace/CreateTeamForm.vue'),
);

const props = defineProps<{
    currentRole?: string | null;
}>();

const { teams, loading, refetch } = useTeams();

const canManage = props.currentRole === 'owner' || props.currentRole === 'admin';

onMounted(() => {
    if (typeof window === 'undefined') return;
    void refetch();
});
</script>

<template>
    <Head title="Equipos" />

    <div class="flex h-full flex-1 flex-col overflow-hidden">
        <!-- Header -->
        <header class="flex shrink-0 items-center gap-3 border-b border-border px-5 py-3">
            <Users class="size-4 text-muted-foreground" />
            <h1 class="text-[13px] font-medium">Equipos</h1>
            <span v-if="teams.length" class="text-[12px] text-muted-foreground">
                {{ teams.length }}
            </span>
            <Loader2 v-if="loading" class="ml-auto size-3.5 animate-spin text-muted-foreground" />
        </header>

        <div class="flex flex-1 flex-col gap-6 overflow-y-auto px-5 py-4">
            <!-- Teams list -->
            <div v-if="loading && !teams.length" class="flex items-center gap-2 py-6 text-[13px] text-muted-foreground">
                <Loader2 class="size-3.5 animate-spin" />
                <span>Cargando equipos&hellip;</span>
            </div>

            <div v-else-if="!teams.length" class="py-8 text-center text-[13px] text-muted-foreground">
                No hay equipos todav&#xED;a.
            </div>

            <ul v-else class="divide-y divide-border rounded-md border border-border">
                <li
                    v-for="team in teams"
                    :key="team.key"
                    class="flex items-center gap-3 px-4 py-3 hover:bg-accent/40"
                >
                    <!-- Color dot -->
                    <span
                        class="size-2.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: team.color ?? '#6366f1' }"
                    />

                    <!-- Key badge -->
                    <span class="shrink-0 rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] font-semibold uppercase text-muted-foreground">
                        {{ team.key }}
                    </span>

                    <!-- Name -->
                    <span class="min-w-0 flex-1 truncate text-[13px] font-medium text-foreground">
                        {{ team.name }}
                    </span>

                    <!-- Stats -->
                    <div class="flex shrink-0 items-center gap-3 text-[12px] text-muted-foreground">
                        <span title="Issues">{{ team.issue_count }} issues</span>
                        <span title="Miembros">{{ team.member_count }} miembros</span>
                    </div>

                    <!-- Settings link -->
                    <Link
                        :href="`/teams/${team.key}/settings`"
                        class="shrink-0 rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        title="Configuraci&#xF3;n del equipo"
                    >
                        <Settings class="size-3.5" />
                    </Link>
                </li>
            </ul>

            <!-- Create form — only for owner/admin -->
            <CreateTeamForm v-if="canManage" />
        </div>
    </div>
</template>
