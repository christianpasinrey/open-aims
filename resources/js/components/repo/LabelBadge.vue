<script setup lang="ts">
import { computed } from 'vue';
import {
    HoverCard,
    HoverCardContent,
    HoverCardTrigger,
} from '@/components/ui/hover-card';
import { useLabelPreview } from '@/composables/useEntityPreview';

const props = withDefaults(
    defineProps<{
        id?: number | null;
        name: string;
        color?: string | null;
        size?: 'sm' | 'md';
    }>(),
    { size: 'sm' },
);

const dotColor = computed<string>(() => props.color ?? '#94a3b8');
const hasHover = computed<boolean>(() => typeof props.id === 'number' && props.id > 0);

const store = useLabelPreview();
const entry = computed(() => (props.id ? store.get(props.id) : { status: 'idle' as const }));

function onOpen(open: boolean): void {
    if (open && props.id) {
        void store.fetchPreview(props.id);
    }
}
</script>

<template>
    <HoverCard v-if="hasHover" :open-delay="200" :close-delay="80" @update:open="onOpen">
        <HoverCardTrigger as-child>
            <span
                :class="[
                    'inline-flex shrink-0 items-center gap-1 rounded-full border border-border bg-card text-foreground',
                    size === 'sm'
                        ? 'px-1.5 py-px text-[11px] leading-[16px]'
                        : 'px-2 py-0.5 text-[12px]',
                ]"
            >
                <span
                    class="size-1.5 shrink-0 rounded-full"
                    :style="{ backgroundColor: dotColor }"
                    aria-hidden="true"
                ></span>
                <span class="truncate">{{ name }}</span>
            </span>
        </HoverCardTrigger>
        <HoverCardContent class="w-64">
            <template v-if="entry.status === 'ready'">
                <div class="flex items-center gap-1.5 text-[13px] font-medium text-foreground">
                    <span
                        class="size-2 shrink-0 rounded-full"
                        :style="{ backgroundColor: entry.data.color ?? dotColor }"
                    ></span>
                    <span class="truncate">{{ entry.data.name }}</span>
                </div>
                <p
                    v-if="entry.data.description"
                    class="mt-1.5 text-[12px] leading-snug text-muted-foreground"
                >
                    {{ entry.data.description }}
                </p>
                <div
                    class="mt-2 flex items-center justify-between gap-2 border-t border-border/60 pt-2 text-[11px] text-muted-foreground"
                >
                    <span class="truncate">
                        {{ entry.data.team ? entry.data.team.name : 'Workspace' }}
                    </span>
                    <span>{{ entry.data.issues.total }} issues</span>
                </div>
            </template>
            <template v-else-if="entry.status === 'error'">
                <div class="text-[12px] text-muted-foreground">Could not load label.</div>
            </template>
            <template v-else>
                <div class="h-3 w-2/3 animate-pulse rounded bg-muted"></div>
                <div class="mt-1.5 h-3 w-1/2 animate-pulse rounded bg-muted"></div>
            </template>
        </HoverCardContent>
    </HoverCard>

    <span
        v-else
        :class="[
            'inline-flex shrink-0 items-center gap-1 rounded-full border border-border bg-card text-foreground',
            size === 'sm'
                ? 'px-1.5 py-px text-[11px] leading-[16px]'
                : 'px-2 py-0.5 text-[12px]',
        ]"
    >
        <span
            class="size-1.5 shrink-0 rounded-full"
            :style="{ backgroundColor: dotColor }"
            aria-hidden="true"
        ></span>
        <span class="truncate">{{ name }}</span>
    </span>
</template>
