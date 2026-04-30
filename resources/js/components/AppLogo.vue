<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const workspaceName = computed<string>(() => {
    const ws = (page.props as { workspace?: { name?: string } }).workspace;
    return ws?.name ?? 'AIMS';
});

const initial = computed<string>(() => workspaceName.value.charAt(0).toUpperCase());
</script>

<template>
    <div
        class="flex aspect-square size-7 items-center justify-center rounded-md bg-brand text-brand-foreground text-xs font-semibold"
    >
        {{ initial }}
    </div>
    <div class="ml-1 grid flex-1 text-left">
        <span class="truncate text-sm font-medium leading-tight">{{ workspaceName }}</span>
    </div>
</template>
