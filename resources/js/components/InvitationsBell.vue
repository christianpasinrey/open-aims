<script setup lang="ts">
import { Bell } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useInvitations } from '@/composables/useInvitations';

const { items, count, accept, decline, start, stop } = useInvitations();

const ROLE_LABELS: Record<string, string> = {
    admin: 'Administrador',
    member: 'Miembro',
    guest: 'Invitado',
};

function roleLabel(role: string): string {
    return ROLE_LABELS[role] ?? role;
}

onMounted(() => {
    if (typeof window === 'undefined') return;
    start();
});

onBeforeUnmount(() => stop());
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="relative rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-foreground"
                aria-label="Invitaciones pendientes"
                title="Invitaciones"
            >
                <Bell class="size-3.5" />
                <span
                    v-if="count > 0"
                    class="absolute -top-0.5 -right-0.5 flex size-4 items-center justify-center rounded-full bg-brand text-[9px] font-bold leading-none text-brand-foreground"
                >
                    {{ count > 9 ? '9+' : count }}
                </span>
            </button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            align="end"
            :side-offset="6"
            class="w-80"
        >
            <DropdownMenuLabel class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                Invitaciones pendientes
            </DropdownMenuLabel>

            <DropdownMenuSeparator />

            <!-- Empty state -->
            <p
                v-if="!items.length"
                class="px-3 py-4 text-center text-[12.5px] text-muted-foreground"
            >
                No tienes invitaciones pendientes.
            </p>

            <!-- Invitation rows -->
            <ul v-else class="divide-y divide-border">
                <li
                    v-for="inv in items"
                    :key="inv.id"
                    class="flex flex-col gap-2 px-3 py-3"
                >
                    <div>
                        <p class="text-[13px] font-medium text-foreground">
                            {{ inv.workspace.name }}
                        </p>
                        <p class="text-[12px] text-muted-foreground">
                            Rol: {{ roleLabel(inv.role) }}
                            <template v-if="inv.invited_by">
                                &middot; Invitado por {{ inv.invited_by.name }}
                            </template>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex h-7 flex-1 items-center justify-center rounded-md bg-brand px-2.5 text-[12px] font-medium text-brand-foreground transition-opacity hover:opacity-90"
                            @click="accept(inv.id)"
                        >
                            Aceptar
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-7 flex-1 items-center justify-center rounded-md px-2.5 text-[12px] font-medium text-destructive ring-1 ring-destructive/40 transition-colors hover:bg-destructive/10"
                            @click="decline(inv.id)"
                        >
                            Rechazar
                        </button>
                    </div>
                </li>
            </ul>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
