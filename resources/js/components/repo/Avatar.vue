<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        name?: string | null;
        email?: string | null;
        size?: number;
    }>(),
    { size: 20 },
);

const palette = [
    '#a78bfa',
    '#60a5fa',
    '#34d399',
    '#fbbf24',
    '#fb7185',
    '#f472b6',
    '#22d3ee',
    '#facc15',
    '#fb923c',
    '#84cc16',
    '#f87171',
    '#818cf8',
];

function hash(s: string): number {
    let h = 0;
    for (let i = 0; i < s.length; i++) {
        h = (h << 5) - h + s.charCodeAt(i);
        h |= 0;
    }
    return Math.abs(h);
}

const initials = computed<string>(() => {
    const n = (props.name ?? '').trim();
    if (!n) return '?';
    return n
        .split(/\s+/)
        .slice(0, 2)
        .map((p) => p.charAt(0).toUpperCase())
        .join('');
});

const bg = computed<string>(() => {
    const seed = props.email ?? props.name ?? '';
    if (!seed) return '#52525b';
    return palette[hash(seed) % palette.length] ?? '#52525b';
});

const fontSize = computed<number>(() => Math.max(8, Math.round(props.size * 0.42)));
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center rounded-full font-medium leading-none text-white"
        :style="{
            width: `${size}px`,
            height: `${size}px`,
            backgroundColor: bg,
            fontSize: `${fontSize}px`,
        }"
        :title="name ?? undefined"
    >
        {{ initials }}
    </span>
</template>
