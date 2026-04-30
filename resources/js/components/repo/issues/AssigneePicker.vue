<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import Avatar from '@/components/repo/Avatar.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import PropertyRow from './PropertyRow.vue';

type Member = { id: number; name: string; email: string };

const props = defineProps<{
    identifier: string;
    current: { id: number; name: string; email: string } | null;
}>();

const open = ref(false);
const members = ref<Member[]>([]);
const loaded = ref(false);
const loading = ref(false);
const query = ref('');
const searchEl = ref<HTMLInputElement | null>(null);

async function loadMembers(): Promise<void> {
    if (loaded.value || loading.value) {
        return;
    }

    loading.value = true;

    try {
        const res = await fetch('/workspace/members', {
            headers: { Accept: 'application/json' },
        });

        if (!res.ok) {
            return;
        }

        const json = (await res.json()) as { data?: Member[] };
        members.value = json.data ?? [];
        loaded.value = true;
    } catch {
        // ignore
    } finally {
        loading.value = false;
    }
}

watch(open, async (isOpen) => {
    if (isOpen) {
        await loadMembers();
        await nextTick();
        searchEl.value?.focus();
    } else {
        query.value = '';
    }
});

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();

    if (!q) {
        return members.value;
    }

    return members.value.filter(
        (m) =>
            m.name.toLowerCase().includes(q) ||
            m.email.toLowerCase().includes(q),
    );
});

function pick(userId: number | null): void {
    if ((props.current?.id ?? null) === userId) {
        open.value = false;

        return;
    }

    router.patch(
        `/issues/${props.identifier}`,
        { assignee_user_id: userId },
        { preserveScroll: true },
    );
    open.value = false;
}
</script>

<template>
    <DropdownMenu v-model:open="open">
        <DropdownMenuTrigger as-child>
            <PropertyRow :empty="!current" label="Assignee">
                <template v-if="current">
                    <Avatar
                        :name="current.name"
                        :email="current.email"
                        :size="18"
                    />
                    <span>{{ current.name }}</span>
                </template>
                <template v-else>
                    <span
                        class="size-3.5 rounded-full border border-dashed border-border"
                    ></span>
                    <span>Unassigned</span>
                </template>
            </PropertyRow>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="w-64 p-0">
            <div class="border-b border-border p-1.5">
                <input
                    ref="searchEl"
                    v-model="query"
                    type="text"
                    placeholder="Assign to…"
                    class="w-full rounded bg-transparent px-1.5 py-1 text-[13px] text-foreground placeholder:text-muted-foreground focus:outline-none"
                    @keydown.stop
                />
            </div>
            <div class="max-h-72 overflow-y-auto p-1">
                <DropdownMenuItem @select="pick(null)">
                    <span
                        class="size-4 rounded-full border border-dashed border-border"
                    ></span>
                    <span class="flex-1">Unassigned</span>
                    <Check v-if="!current" class="size-3.5 text-foreground" />
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <div
                    v-if="!loaded"
                    class="px-2 py-1.5 text-xs text-muted-foreground"
                >
                    Loading…
                </div>
                <template v-else>
                    <DropdownMenuItem
                        v-for="m in filtered"
                        :key="m.id"
                        @select="pick(m.id)"
                    >
                        <Avatar :name="m.name" :email="m.email" :size="18" />
                        <div class="flex min-w-0 flex-1 flex-col">
                            <span class="truncate">{{ m.name }}</span>
                            <span
                                class="truncate text-[11px] text-muted-foreground"
                                >{{ m.email }}</span
                            >
                        </div>
                        <Check
                            v-if="current?.id === m.id"
                            class="size-3.5 text-foreground"
                        />
                    </DropdownMenuItem>
                    <div
                        v-if="filtered.length === 0"
                        class="px-2 py-1.5 text-xs text-muted-foreground"
                    >
                        No matches
                    </div>
                </template>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
