<script setup lang="ts">
import { Copy, KeyRound, Trash2 } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { destroy, index, store } from '@/routes/settings/developer/tokens';

type PersonalToken = {
    id: string;
    name: string | null;
    created_at: string | null;
    expires_at: string | null;
};

const tokens = ref<PersonalToken[]>([]);
const loading = ref(true);
const loadFailed = ref(false);

const name = ref('');
const nameError = ref('');
const generating = ref(false);

// The secret of the token just generated. Held in memory only: the server
// never returns it again, and a reload drops it.
const freshToken = ref<{ name: string | null; token: string } | null>(null);

const confirmingId = ref<string | null>(null);
const revokingId = ref<string | null>(null);

function csrfToken(): string {
    return (
        (
            document.querySelector(
                'meta[name="csrf-token"]',
            ) as HTMLMetaElement | null
        )?.content ?? ''
    );
}

function writeHeaders(): Record<string, string> {
    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
    };
}

function formatDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

async function load() {
    loadFailed.value = false;

    try {
        const res = await fetch(index().url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (!res.ok) {
            throw new Error(String(res.status));
        }

        const json = await res.json();
        tokens.value = Array.isArray(json.tokens) ? json.tokens : [];
    } catch {
        loadFailed.value = true;
    } finally {
        loading.value = false;
    }
}

async function generate() {
    if (generating.value) {
        return;
    }

    nameError.value = '';
    generating.value = true;

    try {
        const res = await fetch(store().url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: writeHeaders(),
            body: JSON.stringify({ name: name.value }),
        });

        if (res.status === 422) {
            const json = await res.json();
            nameError.value =
                json?.errors?.name?.[0] ?? 'Give the token a name.';

            return;
        }

        if (res.status === 429) {
            nameError.value =
                'Too many tokens generated. Try again in a minute.';

            return;
        }

        if (!res.ok) {
            throw new Error(String(res.status));
        }

        const json = await res.json();
        freshToken.value = { name: json.name, token: json.token };
        name.value = '';
        await load();
    } catch {
        toast.error('Could not generate the token');
    } finally {
        generating.value = false;
    }
}

async function revoke(id: string) {
    revokingId.value = id;

    try {
        const res = await fetch(destroy(id).url, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: writeHeaders(),
        });

        if (!res.ok && res.status !== 404) {
            throw new Error(String(res.status));
        }

        toast.success('Token revoked');
        await load();
    } catch {
        toast.error('Could not revoke the token');
    } finally {
        revokingId.value = null;
        confirmingId.value = null;
    }
}

function copyFreshToken() {
    if (!freshToken.value || !navigator.clipboard) {
        toast.error('Could not copy');

        return;
    }

    navigator.clipboard
        .writeText(freshToken.value.token)
        .then(() => toast.success('Token copied'))
        .catch(() => toast.error('Could not copy'));
}

onMounted(load);
</script>

<template>
    <section class="space-y-4" data-test="personal-tokens">
        <form class="flex items-start gap-2" @submit.prevent="generate">
            <div class="flex-1 space-y-1">
                <Input
                    v-model="name"
                    maxlength="60"
                    placeholder="Token name, e.g. Herdr on my laptop"
                    aria-label="Token name"
                    data-test="personal-token-name"
                />
                <p
                    v-if="nameError"
                    class="text-[12px] text-destructive"
                    role="alert"
                >
                    {{ nameError }}
                </p>
            </div>
            <Button
                type="submit"
                :disabled="generating"
                data-test="personal-token-generate"
            >
                <KeyRound class="size-4" />
                Generate token
            </Button>
        </form>

        <div
            v-if="freshToken"
            class="space-y-2 rounded-md border border-emerald-500/30 bg-emerald-500/10 px-4 py-3"
            data-test="personal-token-secret"
        >
            <div class="text-[13px] font-medium text-foreground">
                {{ freshToken.name }}
            </div>
            <div class="flex items-center gap-2">
                <code
                    class="min-w-0 flex-1 truncate rounded border border-border bg-card px-2 py-1.5 font-mono text-[12px]"
                    >{{ freshToken.token }}</code
                >
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="copyFreshToken"
                >
                    <Copy class="size-3.5" />
                    Copy
                </Button>
            </div>
            <p class="text-[12px] text-muted-foreground">
                This token will not be shown again. Store it now.
            </p>
        </div>

        <p v-if="loading" class="text-[13px] text-muted-foreground">
            Loading tokens…
        </p>
        <p v-else-if="loadFailed" class="text-[13px] text-destructive">
            Could not load your tokens.
            <button type="button" class="underline" @click="load">
                Retry
            </button>
        </p>
        <p
            v-else-if="tokens.length === 0"
            class="text-[13px] text-muted-foreground"
        >
            No personal tokens yet.
        </p>
        <ul
            v-else
            class="divide-y divide-border rounded-md border border-border"
        >
            <li
                v-for="t in tokens"
                :key="t.id"
                class="flex items-center justify-between gap-3 px-4 py-3"
            >
                <div class="min-w-0">
                    <div
                        class="truncate text-[13px] font-medium text-foreground"
                    >
                        {{ t.name || 'Unnamed token' }}
                    </div>
                    <div class="text-[11.5px] text-muted-foreground">
                        Created {{ formatDate(t.created_at) }} · Expires
                        {{ formatDate(t.expires_at) }}
                    </div>
                </div>

                <div
                    v-if="confirmingId === t.id"
                    class="flex shrink-0 items-center gap-2"
                >
                    <span class="text-[12px] text-muted-foreground"
                        >Revoke?</span
                    >
                    <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        :disabled="revokingId === t.id"
                        @click="revoke(t.id)"
                    >
                        Revoke
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="confirmingId = null"
                    >
                        Cancel
                    </Button>
                </div>
                <Button
                    v-else
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="shrink-0"
                    :aria-label="`Revoke ${t.name ?? 'token'}`"
                    @click="confirmingId = t.id"
                >
                    <Trash2 class="size-3.5" />
                    Revoke
                </Button>
            </li>
        </ul>
    </section>
</template>
