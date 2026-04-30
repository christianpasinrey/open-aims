<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

type Member = {
    id: number;
    role: string | null;
    user: { id: number; name: string; email: string };
};

defineProps<{
    team: { id: number; name: string; key: string; color: string | null };
    members: Member[];
}>();

function initials(name: string) {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map(p => p.charAt(0).toUpperCase())
        .join('');
}
</script>

<template>
    <Head :title="`${team.name} · Members`" />

    <div class="flex h-full flex-1 flex-col overflow-hidden">
        <header class="flex shrink-0 items-center gap-2 border-b border-border px-5 py-3">
            <span
                class="flex size-6 items-center justify-center rounded-md text-[10px] font-semibold text-white"
                :style="{ backgroundColor: team.color || '#6366f1' }"
            >
                {{ team.key.charAt(0) }}
            </span>
            <h1 class="text-[13px] font-medium">{{ team.name }} · Members</h1>
            <span class="text-[12px] text-muted-foreground">{{ members.length }}</span>
        </header>

        <ul v-if="members.length" class="flex-1 divide-y divide-border overflow-y-auto">
            <li
                v-for="m in members"
                :key="m.id"
                class="flex items-center gap-3 px-5 py-3"
            >
                <span class="flex size-8 items-center justify-center rounded-full bg-muted text-[11px] font-medium">
                    {{ initials(m.user.name) }}
                </span>
                <div class="min-w-0 flex-1">
                    <div class="text-[13.5px] font-medium text-foreground">{{ m.user.name }}</div>
                    <div class="text-[12px] text-muted-foreground">{{ m.user.email }}</div>
                </div>
                <span
                    v-if="m.role"
                    class="rounded-full border border-border bg-card px-2 py-0.5 text-[11px] capitalize text-muted-foreground"
                >{{ m.role }}</span>
            </li>
        </ul>
        <div v-else class="flex flex-1 items-center justify-center px-6 py-12">
            <p class="text-sm text-muted-foreground">No members.</p>
        </div>
    </div>
</template>
