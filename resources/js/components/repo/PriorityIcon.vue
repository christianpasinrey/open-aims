<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        priority?: number;
        size?: number;
        title?: string;
    }>(),
    { priority: 0, size: 14 },
);

const p = computed<number>(() => {
    const v = Number(props.priority ?? 0);
    return [0, 1, 2, 3, 4].includes(v) ? v : 0;
});

// repo's actual values: 1=Urgent, 2=High, 3=Medium, 4=Low, 0=No priority.
// Bars: indices 0,1,2 (short to tall). Filled count by priority.
const filledCount = computed<number>(() => {
    switch (p.value) {
        case 2:
            return 3;
        case 3:
            return 2;
        case 4:
            return 1;
        default:
            return 0;
    }
});

const accent = computed<string>(() => {
    switch (p.value) {
        case 1:
            return '#f87171'; // urgent — handled separately as filled box
        case 2:
            return '#f97316';
        case 3:
            return '#f59e0b';
        case 4:
            return '#a1a1aa';
        default:
            return '#a1a1aa';
    }
});
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 14 14"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        aria-hidden="true"
        role="img"
        :title="title"
        class="shrink-0"
    >
        <!-- URGENT: filled red rounded square with exclamation mark -->
        <template v-if="p === 1">
            <rect
                x="1"
                y="1"
                width="12"
                height="12"
                rx="2.5"
                fill="#ef4444"
            />
            <rect
                x="6.4"
                y="3.4"
                width="1.2"
                height="5"
                rx="0.6"
                fill="white"
            />
            <rect
                x="6.4"
                y="9.4"
                width="1.2"
                height="1.4"
                rx="0.6"
                fill="white"
            />
        </template>

        <!-- NO PRIORITY: three short horizontal dashes -->
        <template v-else-if="p === 0">
            <rect x="2" y="6.4" width="2.6" height="1.2" rx="0.6" fill="#a1a1aa" />
            <rect x="5.7" y="6.4" width="2.6" height="1.2" rx="0.6" fill="#a1a1aa" />
            <rect x="9.4" y="6.4" width="2.6" height="1.2" rx="0.6" fill="#a1a1aa" />
        </template>

        <!-- LOW / MEDIUM / HIGH: 3 vertical bars, filled by priority -->
        <template v-else>
            <rect
                x="1.5"
                y="9"
                width="2.5"
                height="3.5"
                rx="0.6"
                :fill="filledCount >= 1 ? accent : '#52525b'"
                :opacity="filledCount >= 1 ? 1 : 0.45"
            />
            <rect
                x="5.75"
                y="6"
                width="2.5"
                height="6.5"
                rx="0.6"
                :fill="filledCount >= 2 ? accent : '#52525b'"
                :opacity="filledCount >= 2 ? 1 : 0.45"
            />
            <rect
                x="10"
                y="3"
                width="2.5"
                height="9.5"
                rx="0.6"
                :fill="filledCount >= 3 ? accent : '#52525b'"
                :opacity="filledCount >= 3 ? 1 : 0.45"
            />
        </template>
    </svg>
</template>
