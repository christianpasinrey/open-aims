<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        name: string;
        color?: string | null;
        size?: 'sm' | 'md';
    }>(),
    { size: 'sm' },
);

const dotColor = computed<string>(() => props.color ?? '#94a3b8');
</script>

<template>
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
</template>
