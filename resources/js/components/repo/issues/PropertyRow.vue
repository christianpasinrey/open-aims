<script setup lang="ts">
/**
 * Thin wrapper around the dropdown trigger. Used as the clickable row for
 * every property in the right rail. Dashed empty-state styling kicks in via
 * the `empty` prop.
 */
defineProps<{
    empty?: boolean;
    label?: string;
}>();
</script>

<template>
    <button
        type="button"
        class="flex w-full items-center gap-2 rounded px-1 py-1 text-left text-[13px] hover:bg-accent/60"
        :class="empty ? 'text-muted-foreground' : 'text-foreground'"
        :aria-label="label"
    >
        <slot />
    </button>
</template>
