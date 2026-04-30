<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import {
    HoverCard,
    HoverCardContent,
    HoverCardTrigger,
} from '@/components/ui/hover-card';
import Avatar from '@/components/repo/Avatar.vue';
import PriorityIcon from '@/components/repo/PriorityIcon.vue';
import ProjectIcon from '@/components/repo/ProjectIcon.vue';
import StatusIcon from '@/components/repo/StatusIcon.vue';
import { useIssuePreview } from '@/composables/useIssuePreview';

const props = defineProps<{
    identifier: string;
    /** When true, render the trigger as a plain inline span instead of a link. */
    plain?: boolean;
}>();

const { get, fetchPreview } = useIssuePreview();
const entry = computed(() => get(props.identifier));

function handleOpenChange(open: boolean): void {
    if (open) {
        void fetchPreview(props.identifier);
    }
}

watch(
    () => props.identifier,
    () => {
        // Cache is keyed by identifier, no special action needed.
    },
);
</script>

<template>
    <HoverCard :open-delay="200" :close-delay="80" @update:open="handleOpenChange">
        <HoverCardTrigger as-child>
            <Link
                v-if="!plain"
                :href="`/issues/${identifier}`"
                class="rounded font-mono text-[0.92em] text-foreground decoration-muted-foreground/40 underline-offset-2 hover:bg-accent/50 hover:underline"
            >
                <slot>{{ identifier }}</slot>
            </Link>
            <span
                v-else
                class="cursor-default rounded font-mono text-[0.92em] text-foreground hover:bg-accent/50"
            >
                <slot>{{ identifier }}</slot>
            </span>
        </HoverCardTrigger>

        <HoverCardContent
            :side-offset="6"
            class="w-80"
        >
            <template v-if="entry.status === 'loading' || entry.status === 'idle'">
                <div class="flex items-center gap-2 text-muted-foreground">
                    <span class="size-3.5 animate-pulse rounded-full bg-muted"></span>
                    <span class="font-mono text-[12px]">{{ identifier }}</span>
                </div>
                <div class="mt-2 h-3 w-3/4 animate-pulse rounded bg-muted"></div>
                <div class="mt-1.5 h-3 w-1/2 animate-pulse rounded bg-muted"></div>
            </template>

            <template v-else-if="entry.status === 'error'">
                <div class="text-muted-foreground">
                    <span class="font-mono text-[12px]">{{ identifier }}</span>
                    <p class="mt-1 text-[12px]">Could not load preview.</p>
                </div>
            </template>

            <template v-else-if="entry.status === 'ready'">
                <div class="flex items-center justify-between gap-2 text-[11px] text-muted-foreground">
                    <span class="font-mono">{{ entry.data.identifier }}</span>
                    <span
                        v-if="entry.data.assignee"
                        class="flex items-center gap-1.5 text-foreground/80"
                    >
                        <Avatar
                            :name="entry.data.assignee.name"
                            :email="entry.data.assignee.email"
                            :size="14"
                        />
                        <span class="text-[12px]">{{ entry.data.assignee.name }}</span>
                    </span>
                </div>
                <div class="mt-1.5 text-[13px] font-medium leading-snug text-foreground">
                    {{ entry.data.title }}
                </div>
                <div class="mt-2 space-y-1 text-[12px] text-foreground">
                    <div v-if="entry.data.state" class="flex items-center gap-2">
                        <StatusIcon
                            :type="entry.data.state.type"
                            :color="entry.data.state.color"
                        />
                        <span>{{ entry.data.state.name }}</span>
                    </div>
                    <div v-if="entry.data.project" class="flex items-center gap-2">
                        <ProjectIcon
                            :color="entry.data.project.color"
                            :icon="entry.data.project.icon"
                            :size="14"
                        />
                        <span class="min-w-0 truncate">{{ entry.data.project.name }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <PriorityIcon :priority="entry.data.priority" />
                        <span>{{ entry.data.priority_label }}</span>
                    </div>
                </div>
            </template>
        </HoverCardContent>
    </HoverCard>
</template>
