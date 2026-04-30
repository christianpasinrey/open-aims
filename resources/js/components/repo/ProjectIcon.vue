<script setup lang="ts">
import { Box } from 'lucide-vue-next';
import { computed } from 'vue';
import { resolveEmoji } from '@/lib/emoji';

const props = withDefaults(
    defineProps<{
        icon?: string | null;
        color?: string | null;
        size?: number;
        rounded?: 'sm' | 'md' | 'lg';
    }>(),
    { size: 20, rounded: 'md' },
);

const emoji = computed<string | null>(() => resolveEmoji(props.icon ?? null));
const tint = computed<string>(() => props.color ?? '#6366f1');

const radiusClass = computed<string>(() => {
    if (props.rounded === 'sm') {
        return 'rounded';
    }

    if (props.rounded === 'lg') {
        return 'rounded-lg';
    }

    return 'rounded-md';
});

const fontSize = computed<number>(() => Math.round(props.size * 0.62));
</script>

<template>
    <span
        :class="[
            'relative inline-flex shrink-0 items-center justify-center overflow-hidden',
            radiusClass,
        ]"
        :style="{ width: `${size}px`, height: `${size}px` }"
        aria-hidden="true"
    >
        <span
            class="absolute inset-0"
            :style="{ backgroundColor: tint, opacity: 0.18 }"
        ></span>
        <span
            v-if="emoji"
            class="relative leading-none"
            :style="{ fontSize: `${fontSize}px` }"
            >{{ emoji }}</span
        >
        <Box
            v-else
            class="relative"
            :style="{
                color: tint,
                width: `${Math.round(size * 0.6)}px`,
                height: `${Math.round(size * 0.6)}px`,
            }"
        />
    </span>
</template>
