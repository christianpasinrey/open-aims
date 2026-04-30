<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ProjectIcon from '@/components/repo/ProjectIcon.vue';

defineProps<{
    name: string;
    slug?: string | null;
    color?: string | null;
    icon?: string | null;
    href?: string | null;
}>();
</script>

<template>
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
</template>
