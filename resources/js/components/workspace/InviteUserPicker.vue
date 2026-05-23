<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { useUserSearch, type UserSearchResult } from '@/composables/useUserSearch';

const { query, results, loading } = useUserSearch();

const role = ref<'admin' | 'member' | 'guest'>('member');

// Track locally-invited emails so we can disable the button immediately
// without waiting for a page reload.
const locallyInvited = ref<Set<string>>(new Set());

function isInvited(row: UserSearchResult): boolean {
    return row.invited || locallyInvited.value.has(row.email);
}

function invite(row: UserSearchResult): void {
    if (isInvited(row)) return;

    router.post(
        '/workspace/invitations',
        { email: row.email, role: role.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                locallyInvited.value = new Set([...locallyInvited.value, row.email]);
            },
        },
    );
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <!-- Role selector + search input row -->
        <div class="flex gap-2">
            <select
                v-model="role"
                class="h-9 rounded-md border border-input bg-transparent px-2.5 text-[12.5px] text-foreground outline-none focus-visible:border-ring"
            >
                <option value="admin">Administrador</option>
                <option value="member">Miembro</option>
                <option value="guest">Invitado</option>
            </select>

            <input
                v-model="query"
                type="text"
                placeholder="Buscar usuario por nombre o email…"
                class="h-9 min-w-0 flex-1 rounded-md border border-border bg-card px-3 text-[13px] text-foreground placeholder:text-muted-foreground/60 focus:border-ring focus:ring-2 focus:ring-ring/30 focus:outline-none"
            />
        </div>

        <!-- Loading state -->
        <div
            v-if="loading"
            class="flex items-center gap-2 px-1 py-2 text-[12.5px] text-muted-foreground"
        >
            <Loader2 class="size-3.5 animate-spin" />
            <span>Buscando…</span>
        </div>

        <!-- Results list -->
        <ul v-else-if="results.length" class="divide-y divide-border rounded-md border border-border">
            <li
                v-for="row in results"
                :key="row.id"
                class="flex items-center justify-between gap-3 px-3 py-2.5 text-[13px]"
            >
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium text-foreground">{{ row.name }}</p>
                    <p class="truncate text-[12px] text-muted-foreground">{{ row.email }}</p>
                </div>

                <span
                    v-if="isInvited(row)"
                    class="shrink-0 rounded-full bg-muted px-2.5 py-0.5 text-[11px] font-medium text-muted-foreground"
                >
                    Invitado
                </span>

                <button
                    type="button"
                    :disabled="isInvited(row)"
                    class="shrink-0 rounded-md bg-brand px-3 py-1 text-[12px] font-medium text-brand-foreground transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="invite(row)"
                >
                    Invitar
                </button>
            </li>
        </ul>

        <!-- Empty state after search -->
        <p
            v-else-if="query.trim() && !loading"
            class="px-1 py-2 text-[12.5px] text-muted-foreground"
        >
            Sin resultados para &ldquo;{{ query.trim() }}&rdquo;.
        </p>
    </div>
</template>
