<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    HoverCard,
    HoverCardContent,
    HoverCardTrigger,
} from '@/components/ui/hover-card';
import ProjectIcon from '@/components/repo/ProjectIcon.vue';
import { useProjectPreview } from '@/composables/useEntityPreview';

const props = defineProps<{
    name: string;
    slug?: string | null;
    color?: string | null;
    icon?: string | null;
    href?: string | null;
}>();

const hasHover = computed<boolean>(() => typeof props.slug === 'string' && props.slug.length > 0);

const store = useProjectPreview();
const entry = computed(() =>
    props.slug ? store.get(props.slug) : { status: 'idle' as const },
);

function onOpen(open: boolean): void {
    if (open && props.slug) {
        void store.fetchPreview(props.slug);
    }
}

function fmtDate(iso: string | null): string {
    if (!iso) {
        return '';
    }
    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <HoverCard v-if="hasHover" :open-delay="200" :close-delay="80" @update:open="onOpen">
        <HoverCardTrigger as-child>
            <component
                :is="href ? Link : 'span'"
                v-bind="href ? { href } : {}"
                :class="[
                    'inline-flex max-w-[220px] shrink-0 items-center gap-1.5 rounded-md border border-border bg-card px-1.5 py-px text-[11px] leading-[16px] text-foreground',
                    href ? 'hover:bg-accent' : '',
                ]"
            >
                <ProjectIcon :icon="icon" :color="color" :size="14" rounded="sm" />
                <span class="truncate">{{ name }}</span>
            </component>
        </HoverCardTrigger>
        <HoverCardContent class="w-80">
            <template v-if="entry.status === 'ready'">
                <div class="flex items-start gap-2">
                    <ProjectIcon
                        :icon="entry.data.icon"
                        :color="entry.data.color"
                        :size="20"
                        rounded="md"
                    />
                    <div class="min-w-0 flex-1">
                        <div class="text-[13px] font-medium leading-snug text-foreground">
                            {{ entry.data.name }}
                        </div>
                        <p
                            v-if="entry.data.description"
                            class="mt-1 text-[12px] leading-snug text-muted-foreground"
                        >
                            {{ entry.data.description }}
                        </p>
                    </div>
                </div>
                <div
                    class="mt-2.5 grid grid-cols-2 gap-y-1 border-t border-border/60 pt-2 text-[11.5px] text-muted-foreground"
                >
                    <span class="truncate">Status</span>
                    <span class="truncate text-right text-foreground">
                        {{ entry.data.state }}
                    </span>
                    <span v-if="entry.data.lead" class="truncate">Lead</span>
                    <span v-if="entry.data.lead" class="truncate text-right text-foreground">
                        {{ entry.data.lead.name }}
                    </span>
                    <span v-if="entry.data.target_date" class="truncate">Target</span>
                    <span
                        v-if="entry.data.target_date"
                        class="truncate text-right text-foreground"
                    >
                        {{ fmtDate(entry.data.target_date) }}
                    </span>
                    <span class="truncate">Issues</span>
                    <span class="truncate text-right text-foreground">
                        {{ entry.data.issues.completed }} / {{ entry.data.issues.total }}
                        ({{ Math.round(entry.data.issues.progress * 100) }}%)
                    </span>
                </div>
            </template>
            <template v-else-if="entry.status === 'error'">
                <div class="text-[12px] text-muted-foreground">Could not load project.</div>
            </template>
            <template v-else>
                <div class="flex items-start gap-2">
                    <div class="size-5 animate-pulse rounded bg-muted"></div>
                    <div class="flex-1">
                        <div class="h-3 w-3/4 animate-pulse rounded bg-muted"></div>
                        <div class="mt-1.5 h-3 w-1/2 animate-pulse rounded bg-muted"></div>
                    </div>
                </div>
            </template>
        </HoverCardContent>
    </HoverCard>

    <component
        v-else
        :is="href ? Link : 'span'"
        v-bind="href ? { href } : {}"
        :class="[
            'inline-flex max-w-[220px] shrink-0 items-center gap-1.5 rounded-md border border-border bg-card px-1.5 py-px text-[11px] leading-[16px] text-foreground',
            href ? 'hover:bg-accent' : '',
        ]"
    >
        <ProjectIcon :icon="icon" :color="color" :size="14" rounded="sm" />
        <span class="truncate">{{ name }}</span>
    </component>
</template>
