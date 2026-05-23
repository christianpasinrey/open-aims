<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Users, UserPlus, Search, Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Avatar from '@/components/repo/Avatar.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Member = {
    id: number;
    role: string;
    joined_at: string | null;
    user: { id: number; name: string; email: string };
};

const props = defineProps<{
    members: Member[];
    count: number;
    currentRole?: string | null;
}>();

const query = ref('');
const inviteOpen = ref(false);

const canInvite = computed(
    () => props.currentRole === 'owner' || props.currentRole === 'admin',
);

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

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const d = new Date(iso);

    if (Number.isNaN(d.getTime())) {
        return '—';
    }

    return d.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

// Invite form state
const inviteEmail = ref('');
const inviteRole = ref<'admin' | 'member' | 'guest'>('member');
const inviteProcessing = ref(false);
const inviteErrors = ref<{ email?: string; role?: string; invitation?: string }>({});

function submitInvite(): void {
    inviteProcessing.value = true;
    inviteErrors.value = {};

    router.post(
        '/workspace/invitations',
        { email: inviteEmail.value, role: inviteRole.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                inviteEmail.value = '';
                inviteRole.value = 'member';
                inviteOpen.value = false;
            },
            onError: (errors) => {
                inviteErrors.value = errors as typeof inviteErrors.value;
            },
            onFinish: () => {
                inviteProcessing.value = false;
            },
        },
    );
}

function openInviteDialog(): void {
    inviteErrors.value = {};
    inviteOpen.value = true;
}
</script>

<template>
    <Head title="Workspace members" />

    <div class="flex h-full flex-1 flex-col overflow-hidden">
        <header
            class="flex shrink-0 items-center gap-3 border-b border-border px-5 py-3"
        >
            <Users class="size-4 text-muted-foreground" />
            <h1 class="text-[13px] font-medium">Members</h1>
            <span class="text-[12px] text-muted-foreground">{{ count }}</span>
            <div class="ml-auto flex items-center gap-2">
                <Link
                    :href="'/workspace/settings'"
                    class="rounded-md px-2.5 py-1 text-[12.5px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                >
                    Settings
                </Link>
                <Button v-if="canInvite" size="sm" @click="openInviteDialog">
                    <UserPlus class="mr-1 size-3.5" />
                    Invite
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

        <div v-if="filtered.length" class="flex-1 overflow-y-auto">
            <div
                class="sticky top-0 z-10 grid grid-cols-[1fr_2fr_120px_120px] gap-3 border-b border-border bg-muted/40 px-5 py-1.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase backdrop-blur"
            >
                <span>Name</span>
                <span>Email</span>
                <span>Role</span>
                <span>Joined</span>
            </div>
            <ul class="divide-y divide-border">
                <li
                    v-for="m in filtered"
                    :key="m.id"
                    class="grid grid-cols-[1fr_2fr_120px_120px] items-center gap-3 px-5 py-2.5 hover:bg-accent/40"
                >
                    <div class="flex min-w-0 items-center gap-2.5">
                        <Avatar
                            :name="m.user.name"
                            :email="m.user.email"
                            :size="24"
                        />
                        <span class="truncate text-[13px] font-medium">{{
                            m.user.name
                        }}</span>
                    </div>
                    <span
                        class="truncate text-[12.5px] text-muted-foreground"
                        >{{ m.user.email }}</span
                    >
                    <span
                        class="text-[12px] text-muted-foreground capitalize"
                        >{{ m.role }}</span
                    >
                    <span class="text-[12px] text-muted-foreground">{{
                        formatDate(m.joined_at)
                    }}</span>
                </li>
            </ul>
        </div>
        <div v-else class="flex flex-1 items-center justify-center px-6 py-12">
            <p class="text-sm text-muted-foreground">No matching members.</p>
        </div>

        <!-- Invite dialog — only rendered/usable when canInvite is true -->
        <Dialog v-if="canInvite" v-model:open="inviteOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Invitar por email</DialogTitle>
                    <DialogDescription>
                        Envía una invitación a un nuevo miembro del workspace.
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-4" @submit.prevent="submitInvite">
                    <div class="grid gap-1.5">
                        <Label for="invite-email">Correo electrónico</Label>
                        <Input
                            id="invite-email"
                            v-model="inviteEmail"
                            type="email"
                            placeholder="compañero@empresa.com"
                            required
                            :disabled="inviteProcessing"
                            :class="{ 'border-destructive': inviteErrors.email }"
                        />
                        <p v-if="inviteErrors.email" class="text-[12px] text-destructive">
                            {{ inviteErrors.email }}
                        </p>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="invite-role">Rol</Label>
                        <Select v-model="inviteRole" :disabled="inviteProcessing">
                            <SelectTrigger id="invite-role">
                                <SelectValue placeholder="Selecciona un rol" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="admin">Administrador</SelectItem>
                                <SelectItem value="member">Miembro</SelectItem>
                                <SelectItem value="guest">Invitado</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="inviteErrors.role" class="text-[12px] text-destructive">
                            {{ inviteErrors.role }}
                        </p>
                    </div>

                    <p v-if="inviteErrors.invitation" class="text-[12px] text-destructive">
                        {{ inviteErrors.invitation }}
                    </p>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            :disabled="inviteProcessing"
                            @click="inviteOpen = false"
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" :disabled="inviteProcessing">
                            <Loader2 v-if="inviteProcessing" class="mr-1 size-3.5 animate-spin" />
                            {{ inviteProcessing ? 'Enviando…' : 'Invitar' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
