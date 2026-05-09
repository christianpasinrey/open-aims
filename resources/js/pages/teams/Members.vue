<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { UserPlus, Search } from 'lucide-vue-next';
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

type Member = {
    id: number;
    role: string | null;
    user: { id: number; name: string; email: string };
};

const props = defineProps<{
    team: { id: number; name: string; key: string; color: string | null };
    members: Member[];
}>();

const query = ref('');
const inviteOpen = ref(false);

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
</script>

<template>
    <Head :title="`${team.name} · Members`" />

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
                >Members</span
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
                <Button size="sm" @click="inviteOpen = true">
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

        <ul
            v-if="filtered.length"
            class="flex-1 divide-y divide-border overflow-y-auto"
        >
            <li
                v-for="m in filtered"
                :key="m.id"
                class="flex items-center gap-3 px-5 py-2.5 hover:bg-accent/40"
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
                    class="rounded-full border border-border bg-card px-2 py-0.5 text-[11px] text-muted-foreground capitalize"
                    >{{ m.role }}</span
                >
            </li>
        </ul>
        <div v-else class="flex flex-1 items-center justify-center px-6 py-12">
            <p class="text-sm text-muted-foreground">No matching members.</p>
        </div>

        <Dialog v-model:open="inviteOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Invite to {{ team.name }}</DialogTitle>
                    <DialogDescription>
                        Invitations aren&rsquo;t wired up yet. Coming soon.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="invite-email">Email address</Label>
                    <Input
                        id="invite-email"
                        type="email"
                        placeholder="teammate@company.com"
                        disabled
                    />
                </div>
                <DialogFooter>
                    <Button variant="ghost" @click="inviteOpen = false"
                        >Close</Button
                    >
                    <Button disabled>Send invite</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
