<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { GitBranch, GitPullRequest, Plus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';

type LinkedBranch = {
    id: number;
    name: string;
    head_sha: string | null;
    repo_full_name: string;
    html_url: string | null;
    last_pushed_at: string | null;
    link_id: number;
    auto: boolean;
    linked_at: string | null;
};

type LinkedPullRequest = {
    id: number;
    number: number;
    title: string;
    state: string;
    merged: boolean;
    head_branch_name: string | null;
    html_url: string | null;
    link_id: number;
    auto: boolean;
    linked_at: string | null;
};

type AvailableSource = {
    kind: 'branch' | 'pull_request';
    id: number;
    label: string;
    sublabel: string;
};

const props = defineProps<{
    linkableType: 'issue' | 'project';
    linkableId: number;
    branches: LinkedBranch[];
    pullRequests: LinkedPullRequest[];
    available: AvailableSource[];
}>();

const search = ref('');
const submitting = ref(false);

const filtered = computed<AvailableSource[]>(() => {
    const q = search.value.trim().toLowerCase();
    const linkedBranchIds = new Set(props.branches.map((b) => b.id));
    const linkedPrIds = new Set(props.pullRequests.map((p) => p.id));
    return props.available
        .filter((src) => {
            if (src.kind === 'branch' && linkedBranchIds.has(src.id)) {
                return false;
            }
            if (src.kind === 'pull_request' && linkedPrIds.has(src.id)) {
                return false;
            }
            if (q === '') {
                return true;
            }
            return (
                src.label.toLowerCase().includes(q) ||
                src.sublabel.toLowerCase().includes(q)
            );
        })
        .slice(0, 50);
});

const linkableLabel = computed<string>(() =>
    props.linkableType === 'issue' ? 'issue' : 'project',
);

function add(src: AvailableSource): void {
    if (submitting.value) {
        return;
    }
    submitting.value = true;
    router.post(
        '/github-links',
        {
            source_type: src.kind,
            source_id: src.id,
            linkable_type: props.linkableType,
            linkable_id: props.linkableId,
        },
        {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                toast.success(
                    src.kind === 'branch' ? 'Branch linked' : 'Pull request linked',
                );
                search.value = '';
            },
            onError: () => {
                toast.error(`Failed to link ${src.kind.replace('_', ' ')}`);
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}

function remove(linkId: number, kind: 'branch' | 'pull_request'): void {
    if (submitting.value) {
        return;
    }
    submitting.value = true;
    router.delete(`/github-links/${linkId}`, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            toast.success(
                kind === 'branch' ? 'Branch unlinked' : 'Pull request unlinked',
            );
        },
        onError: () => {
            toast.error(`Failed to unlink ${kind.replace('_', ' ')}`);
        },
        onFinish: () => {
            submitting.value = false;
        },
    });
}

function prTone(pr: LinkedPullRequest): string {
    if (pr.merged) {
        return 'text-purple-400';
    }
    if (pr.state === 'closed') {
        return 'text-rose-400';
    }
    return 'text-emerald-400';
}

const totalLinked = computed<number>(
    () => props.branches.length + props.pullRequests.length,
);
</script>

<template>
    <div class="space-y-1">
        <ul v-if="totalLinked > 0" class="flex flex-col">
            <li
                v-for="b in branches"
                :key="`b-${b.link_id}`"
                class="group flex min-h-[34px] items-center gap-2 rounded px-1 py-1 text-[13px] text-foreground transition-colors hover:bg-accent/60"
            >
                <GitBranch class="size-3.5 shrink-0 text-sky-400" />
                <a
                    v-if="b.html_url"
                    :href="b.html_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="min-w-0 flex-1 truncate font-mono text-[12px] hover:underline"
                    :title="b.name"
                >
                    {{ b.name }}
                </a>
                <span
                    v-else
                    class="min-w-0 flex-1 truncate font-mono text-[12px]"
                >
                    {{ b.name }}
                </span>
                <span
                    v-if="b.auto"
                    class="hidden shrink-0 rounded px-1 py-px text-[9px] font-medium tracking-wide text-muted-foreground uppercase ring-1 ring-border group-hover:hidden"
                    :title="`Auto-linked from PR matcher`"
                >
                    Auto
                </span>
                <button
                    type="button"
                    class="hidden size-5 shrink-0 items-center justify-center rounded text-muted-foreground hover:bg-accent hover:text-foreground group-hover:inline-flex"
                    :aria-label="`Unlink branch ${b.name}`"
                    :title="`Unlink branch`"
                    :disabled="submitting"
                    @click.prevent="remove(b.link_id, 'branch')"
                >
                    <X class="size-3" />
                </button>
            </li>
            <li
                v-for="pr in pullRequests"
                :key="`p-${pr.link_id}`"
                class="group flex min-h-[34px] items-center gap-2 rounded px-1 py-1 text-[13px] text-foreground transition-colors hover:bg-accent/60"
            >
                <GitPullRequest
                    class="size-3.5 shrink-0"
                    :class="prTone(pr)"
                />
                <span
                    class="font-mono text-[11px] text-muted-foreground tabular-nums"
                >
                    #{{ pr.number }}
                </span>
                <a
                    v-if="pr.html_url"
                    :href="pr.html_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="min-w-0 flex-1 truncate text-[12.5px] hover:underline"
                    :title="pr.title"
                >
                    {{ pr.title }}
                </a>
                <span v-else class="min-w-0 flex-1 truncate text-[12.5px]">
                    {{ pr.title }}
                </span>
                <span
                    v-if="pr.auto"
                    class="hidden shrink-0 rounded px-1 py-px text-[9px] font-medium tracking-wide text-muted-foreground uppercase ring-1 ring-border group-hover:hidden"
                    title="Auto-linked from PR matcher"
                >
                    Auto
                </span>
                <button
                    type="button"
                    class="hidden size-5 shrink-0 items-center justify-center rounded text-muted-foreground hover:bg-accent hover:text-foreground group-hover:inline-flex"
                    :aria-label="`Unlink PR #${pr.number}`"
                    title="Unlink pull request"
                    :disabled="submitting"
                    @click.prevent="remove(pr.link_id, 'pull_request')"
                >
                    <X class="size-3" />
                </button>
            </li>
        </ul>

        <p
            v-else
            class="px-1 py-1 text-[12.5px] text-muted-foreground"
        >
            No linked branches or PRs.
        </p>

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    class="inline-flex w-full items-center gap-1.5 rounded px-1 py-1 text-[12.5px] text-muted-foreground hover:bg-accent/60 hover:text-foreground"
                    :aria-label="`Link a branch or PR to this ${linkableLabel}`"
                >
                    <Plus class="size-3.5" />
                    <span>Add</span>
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                class="w-[300px] p-2"
                :side-offset="4"
            >
                <Input
                    v-model="search"
                    placeholder="Search branches and PRs..."
                    autofocus
                    class="mb-2 h-8 text-[12.5px]"
                />
                <div
                    v-if="!filtered.length"
                    class="px-2 py-3 text-center text-[12px] text-muted-foreground"
                >
                    {{
                        available.length === 0
                            ? 'No GitHub sources available. Sync the integration first.'
                            : 'No matches.'
                    }}
                </div>
                <ul v-else class="max-h-[280px] overflow-y-auto">
                    <li
                        v-for="src in filtered"
                        :key="`${src.kind}-${src.id}`"
                    >
                        <button
                            type="button"
                            class="flex w-full items-start gap-2 rounded px-2 py-1.5 text-left transition-colors hover:bg-accent/60 disabled:opacity-50"
                            :disabled="submitting"
                            @click="add(src)"
                        >
                            <component
                                :is="
                                    src.kind === 'branch'
                                        ? GitBranch
                                        : GitPullRequest
                                "
                                class="mt-0.5 size-3.5 shrink-0"
                                :class="
                                    src.kind === 'branch'
                                        ? 'text-sky-400'
                                        : 'text-emerald-400'
                                "
                            />
                            <div class="min-w-0 flex-1">
                                <div
                                    class="truncate text-[12.5px] text-foreground"
                                    :class="{
                                        'font-mono':
                                            src.kind === 'branch',
                                    }"
                                >
                                    {{ src.label }}
                                </div>
                                <div
                                    v-if="src.sublabel"
                                    class="truncate text-[11px] text-muted-foreground"
                                >
                                    {{ src.sublabel }}
                                </div>
                            </div>
                        </button>
                    </li>
                </ul>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
