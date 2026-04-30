<script setup lang="ts">
import {
    Archive,
    ChevronDown,
    Copy,
    GitBranch,
    Link as LinkIcon,
    Network,
    Trash2,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import StatusIcon from '@/components/repo/StatusIcon.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type RelatedIssue = {
    identifier: string;
    title: string;
    state: { type: string; color: string } | null;
};

const props = defineProps<{
    identifier: string;
    title: string;
    /** Sibling issues (other open issues in same team), used as duplicate suggestions. */
    related?: RelatedIssue[];
}>();

const branchName = computed(() => {
    const slug = props.title
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[^\w\s-]/g, '')
        .trim()
        .replace(/\s+/g, '-')
        .slice(0, 60);

    return `christianpasinrey/${props.identifier.toLowerCase()}-${slug}`;
});

function copy(text: string, message: string): void {
    if (typeof navigator === 'undefined' || !navigator.clipboard) {
        toast.error('Clipboard not available');

        return;
    }

    navigator.clipboard
        .writeText(text)
        .then(() => toast.success(message))
        .catch(() => toast.error('Could not copy'));
}

function copyLink(): void {
    const url =
        typeof window !== 'undefined'
            ? `${window.location.origin}/issues/${props.identifier}`
            : `/issues/${props.identifier}`;
    copy(url, 'Link copied');
}
function copyId(): void {
    copy(props.identifier, `Copied ${props.identifier}`);
}
function copyBranch(): void {
    copy(branchName.value, 'Branch name copied');
}
</script>

<template>
    <div class="ml-auto flex items-center gap-0.5 text-muted-foreground">
        <button
            type="button"
            class="inline-flex size-7 items-center justify-center rounded-md transition-colors hover:bg-accent hover:text-foreground"
            aria-label="Copy link"
            title="Copy link"
            @click="copyLink"
        >
            <LinkIcon class="size-4" />
        </button>
        <button
            type="button"
            class="inline-flex size-7 items-center justify-center rounded-md transition-colors hover:bg-accent hover:text-foreground"
            aria-label="Copy ID"
            title="Copy ID"
            @click="copyId"
        >
            <Copy class="size-4" />
        </button>
        <button
            type="button"
            class="inline-flex size-7 items-center justify-center rounded-md transition-colors hover:bg-accent hover:text-foreground"
            aria-label="Copy branch name"
            title="Copy branch name"
            @click="copyBranch"
        >
            <GitBranch class="size-4" />
        </button>

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    class="inline-flex size-7 items-center justify-center rounded-md transition-colors hover:bg-accent hover:text-foreground"
                    aria-label="Mark as duplicate"
                    title="Relations"
                >
                    <Network class="size-4" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-72">
                <DropdownMenuLabel>Mark as duplicate of…</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <template v-if="related && related.length">
                    <DropdownMenuItem
                        v-for="r in related.slice(0, 5)"
                        :key="r.identifier"
                        disabled
                    >
                        <StatusIcon
                            :type="r.state?.type ?? 'unstarted'"
                            :color="r.state?.color"
                            :size="14"
                        />
                        <span
                            class="font-mono text-[11px] text-muted-foreground"
                            >{{ r.identifier }}</span
                        >
                        <span class="min-w-0 flex-1 truncate">{{
                            r.title
                        }}</span>
                    </DropdownMenuItem>
                </template>
                <DropdownMenuItem v-else disabled
                    >No suggestions</DropdownMenuItem
                >
            </DropdownMenuContent>
        </DropdownMenu>

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    class="ml-1 inline-flex size-7 items-center justify-center rounded-md transition-colors hover:bg-accent hover:text-foreground"
                    aria-label="More"
                    title="More"
                >
                    <ChevronDown class="size-4" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-44">
                <DropdownMenuItem disabled>
                    <Archive class="size-3.5" />
                    <span>Archive issue</span>
                </DropdownMenuItem>
                <DropdownMenuItem disabled variant="destructive">
                    <Trash2 class="size-3.5" />
                    <span>Delete issue</span>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
