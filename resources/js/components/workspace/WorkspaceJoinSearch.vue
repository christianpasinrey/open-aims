<script setup lang="ts">
import { useWorkspaceSearch } from '@/composables/useWorkspaceSearch';
import { Loader2 } from 'lucide-vue-next';

const emit = defineEmits<{ joined: [] }>();

const { query, results, loading, join } = useWorkspaceSearch();

function handleAction(slug: string): void {
    join(slug);
    emit('joined');
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <input
            v-model="query"
            type="text"
            placeholder="Buscar workspace por nombre…"
            class="h-10 w-full rounded-md border border-border bg-card px-3 text-[14px] text-foreground placeholder:text-muted-foreground/60 focus:border-ring focus:ring-2 focus:ring-ring/30 focus:outline-none"
        />

        <div v-if="loading" class="flex items-center gap-2 px-1 py-2 text-[12.5px] text-muted-foreground">
            <Loader2 class="size-3.5 animate-spin" />
            <span>Buscando…</span>
        </div>

        <ul v-else-if="results.length" class="divide-y divide-border rounded-md border border-border">
            <li
                v-for="ws in results"
                :key="ws.slug"
                class="flex items-center justify-between gap-3 px-3 py-2.5 text-[13px]"
            >
                <span class="min-w-0 flex-1 truncate font-medium text-foreground">
                    {{ ws.name }}
                </span>

                <!-- member -->
                <template v-if="ws.relationship === 'member'">
                    <span class="rounded-full bg-muted px-2.5 py-0.5 text-[11px] font-medium text-muted-foreground">
                        Miembro
                    </span>
                    <button
                        type="button"
                        class="rounded-md bg-card px-3 py-1 text-[12px] font-medium text-foreground ring-1 ring-border transition-colors hover:bg-accent"
                        @click="handleAction(ws.slug)"
                    >
                        Abrir
                    </button>
                </template>

                <!-- open -->
                <template v-else-if="ws.relationship === 'open'">
                    <button
                        type="button"
                        class="rounded-md bg-brand px-3 py-1 text-[12px] font-medium text-brand-foreground transition-opacity hover:opacity-90"
                        @click="handleAction(ws.slug)"
                    >
                        Unirse
                    </button>
                </template>

                <!-- request -->
                <template v-else-if="ws.relationship === 'request'">
                    <button
                        type="button"
                        class="rounded-md bg-brand px-3 py-1 text-[12px] font-medium text-brand-foreground transition-opacity hover:opacity-90"
                        @click="handleAction(ws.slug)"
                    >
                        Solicitar acceso
                    </button>
                </template>

                <!-- pending -->
                <template v-else-if="ws.relationship === 'pending'">
                    <span class="rounded-full bg-muted px-2.5 py-0.5 text-[11px] font-medium text-muted-foreground">
                        Pendiente
                    </span>
                </template>
            </li>
        </ul>

        <p
            v-else-if="query.trim() && !loading"
            class="px-1 py-2 text-[12.5px] text-muted-foreground"
        >
            Sin resultados para &ldquo;{{ query.trim() }}&rdquo;.
        </p>
    </div>
</template>
