<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Search, UserPlus, X } from 'lucide-vue-next';
import { computed, defineAsyncComponent, ref } from 'vue';
import Avatar from '@/components/repo/Avatar.vue';
import { Button } from '@/components/ui/button';

const AddMemberDialog = defineAsyncComponent(
    () => import('@/pages/teams/partials/AddMemberDialog.vue'),
);

type Member = {
    id: number;
    role: string | null;
    user: { id: number; name: string; email: string };
};

const props = defineProps<{
    team: { id: number; name: string; key: string; color: string | null };
    members: Member[];
    currentRole: string | null;
}>();

const query = ref('');
const addOpen = ref(false);

const canManage = computed(
    () => props.currentRole === 'owner' || props.currentRole === 'admin',
);

const roleLabels: Record<string, string> = {
    lead: 'Lead',
    member: 'Miembro',
};

const filtered = computed<Member[]>(() => {
    const q = query.value.trim().toLowerCase();

    if (!q) {
        return props.members;
    }

    return (props.members ?? []).filter(
        (m) =>
            m.user.name.toLowerCase().includes(q) ||
            m.user.email.toLowerCase().includes(q),
    );
});

function removeMember(m: Member) {
    if (!canManage.value) {
        return;
    }

    if (!window.confirm(`¿Quitar a ${m.user.name} del equipo?`)) {
        return;
    }

    router.delete(`/teams/${props.team.key}/members/${m.user.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`${team.name} · Miembros`" />

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
                >Miembros</span
            >
            <span class="text-[12px] text-muted-foreground">{{
                members.length
            }}</span>
            <div class="ml-auto flex items-center gap-2">
                <Link
                    :href="`/teams/${team.key}/labels`"
                    class="rounded-md px-2.5 py-1 text-[12.5px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                >
                    Labels
                </Link>
                <Link
                    :href="`/teams/${team.key}/settings`"
                    class="rounded-md px-2.5 py-1 text-[12.5px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                >
                    Settings
                </Link>
                <Button v-if="canManage" size="sm" @click="addOpen = true">
                    <UserPlus class="mr-1 size-3.5" />
                    Añadir miembro
                </Button>
            </div>
        </header>

        <div
            class="flex shrink-0 items-center gap-2 border-b border-border px-5 py-2"
        >
            <Search class="size-3.5 text-muted-foreground" />
            <input
                v-model="query"
                type="text"
                placeholder="Filter members…"
                class="flex-1 bg-transparent text-[13px] outline-none placeholder:text-muted-foreground"
            />
        </div>

        <ul
            v-if="filtered.length"
            class="flex-1 divide-y divide-border overflow-y-auto"
        >
            <li
                v-for="m in filtered"
                :key="m.id"
                class="group flex items-center gap-3 px-5 py-2.5 hover:bg-accent/40"
            >
                <Avatar :name="m.user.name" :email="m.user.email" :size="28" />
                <div class="min-w-0 flex-1">
                    <div class="text-[13px] font-medium text-foreground">
                        {{ m.user.name }}
                    </div>
                    <div class="text-[12px] text-muted-foreground">
                        {{ m.user.email }}
                    </div>
                </div>
                <span
                    v-if="m.role"
                    class="rounded-full border border-border bg-card px-2 py-0.5 text-[11px] text-muted-foreground"
                    >{{ roleLabels[m.role] ?? m.role }}</span
                >
                <button
                    v-if="canManage"
                    type="button"
                    title="Quitar"
                    class="flex size-6 items-center justify-center rounded-md text-muted-foreground opacity-0 transition-colors group-hover:opacity-100 hover:bg-accent hover:text-foreground focus:opacity-100 focus:outline-none"
                    @click="removeMember(m)"
                >
                    <X class="size-3.5" />
                    <span class="sr-only">Quitar</span>
                </button>
            </li>
        </ul>
        <div v-else class="flex flex-1 items-center justify-center px-6 py-12">
            <p class="text-sm text-muted-foreground">No matching members.</p>
        </div>

        <AddMemberDialog
            v-if="canManage"
            v-model:open="addOpen"
            :team-key="team.key"
            :members="members"
        />
    </div>
</template>
