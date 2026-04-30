<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Users, UserPlus, Search } from 'lucide-vue-next';
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
    role: string;
    joined_at: string | null;
    user: { id: number; name: string; email: string };
};

const props = defineProps<{ members: Member[]; count: number }>();

const query = ref('');
const inviteOpen = ref(false);

const filtered = computed<Member[]>(() => {
    const q = query.value.trim().toLowerCase();

    if (!q) {
        return props.members;
    }

    return props.members.filter(
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

        <Dialog v-model:open="inviteOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Invite a teammate</DialogTitle>
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
