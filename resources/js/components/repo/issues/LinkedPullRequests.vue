<script setup lang="ts">
import { computed, ref } from 'vue';
import { GitPullRequest, GitMerge, Copy, Check } from 'lucide-vue-next';

type PullRequest = {
    id: number;
    number: number;
    title: string;
    state: 'open' | 'closed' | 'merged' | string;
    url: string;
    branch_name: string;
    author_login: string | null;
    opened_at: string | null;
    closed_at: string | null;
    merged_at: string | null;
};

const props = defineProps<{
    pullRequests: PullRequest[];
    branchName?: string | null;
}>();

const count = computed<number>(() => props.pullRequests.length);

const copied = ref(false);
async function copyBranch(): Promise<void> {
    if (!props.branchName) return;
    const cmd = `git checkout -b ${props.branchName}`;
    try {
        await navigator.clipboard.writeText(cmd);
        copied.value = true;
        window.setTimeout(() => {
            copied.value = false;
        }, 1500);
    } catch {
        // ignore — clipboard might be blocked in non-secure contexts
    }
}

function pillClass(state: string): string {
    switch (state) {
        case 'merged':
            return 'bg-purple-500/15 text-purple-400 ring-1 ring-purple-500/30';
        case 'closed':
            return 'bg-rose-500/15 text-rose-400 ring-1 ring-rose-500/30';
        default:
            return 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30';
    }
}

function pillLabel(state: string): string {
    if (state === 'merged') return 'Merged';
    if (state === 'closed') return 'Closed';
    return 'Open';
}
</script>

<template>
    <section v-if="count > 0 || branchName">
        <div
            class="mb-1 text-[10px] font-medium uppercase tracking-wide text-muted-foreground"
        >
            Pull requests
            <span v-if="count > 0" class="ml-1 text-muted-foreground/70"
                >· {{ count }}</span
            >
        </div>

        <ul v-if="count > 0" class="flex flex-col">
            <li v-for="pr in pullRequests" :key="pr.id">
                <a
                    :href="pr.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="group flex min-h-[34px] items-center gap-2 rounded px-1 py-1 text-[13px] text-foreground transition-colors hover:bg-accent/60"
                >
                    <component
                        :is="pr.state === 'merged' ? GitMerge : GitPullRequest"
                        class="size-3.5 shrink-0"
                        :class="
                            pr.state === 'merged'
                                ? 'text-purple-400'
                                : pr.state === 'closed'
                                  ? 'text-rose-400'
                                  : 'text-emerald-400'
                        "
                    />
                    <span
                        class="font-mono text-[11px] text-muted-foreground"
                        >#{{ pr.number }}</span
                    >
                    <span class="min-w-0 flex-1 truncate">{{ pr.title }}</span>
                    <span
                        class="hidden shrink-0 rounded px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide group-hover:inline-flex"
                        :class="pillClass(pr.state)"
                    >
                        {{ pillLabel(pr.state) }}
                    </span>
                </a>
                <div
                    class="mt-0.5 ml-6 flex items-center gap-2 truncate text-[11px] text-muted-foreground"
                >
                    <span class="font-mono truncate">{{ pr.branch_name }}</span>
                    <span v-if="pr.author_login" class="truncate"
                        >· @{{ pr.author_login }}</span
                    >
                </div>
            </li>
        </ul>

        <div
            v-else-if="branchName"
            class="rounded-md border border-dashed border-border bg-muted/30 px-3 py-2.5"
        >
            <div class="text-[12px] text-muted-foreground">No PRs yet.</div>
            <div
                class="mt-2 flex items-center gap-2 rounded bg-background/50 px-2 py-1.5"
            >
                <code class="flex-1 truncate font-mono text-[11px] text-foreground"
                    >git checkout -b {{ branchName }}</code
                >
                <button
                    type="button"
                    class="inline-flex size-6 shrink-0 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-accent/60 hover:text-foreground"
                    :aria-label="copied ? 'Copied' : 'Copy command'"
                    @click="copyBranch"
                >
                    <Check v-if="copied" class="size-3.5 text-emerald-400" />
                    <Copy v-else class="size-3.5" />
                </button>
            </div>
        </div>
    </section>
</template>
