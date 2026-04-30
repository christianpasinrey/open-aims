<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

type WorkspaceProp = {
    name?: string;
    color?: string | null;
    logo_url?: string | null;
};

const workspace = computed<WorkspaceProp>(() => {
    const ws = (page.props as { workspace?: WorkspaceProp }).workspace;

    return ws ?? {};
});

const workspaceName = computed<string>(
    () => workspace.value.name ?? 'AIMS',
);

// Two-letter initials. Single-word names → first two chars uppercased.
// Multi-word names → first letter of first two words. repo's convention.
const initials = computed<string>(() => {
    const name = workspaceName.value.trim();
    if (!name) return 'D';
    const parts = name.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
        return (parts[0]!.charAt(0) + parts[1]!.charAt(0)).toUpperCase();
    }

    return name.slice(0, 2).toUpperCase();
});

const color = computed<string>(() => workspace.value.color ?? '#a855f7');
const logoUrl = computed<string | null>(() => workspace.value.logo_url ?? null);

const displayName = computed<string>(() => workspaceName.value.toLowerCase());
</script>

<template>
    <span
        v-if="!logoUrl"
        class="flex aspect-square size-7 shrink-0 items-center justify-center rounded-full text-[10.5px] font-semibold text-white tracking-tight"
        :style="{ backgroundColor: color }"
    >
        {{ initials }}
    </span>
    <img
        v-else
        :src="logoUrl"
        :alt="workspaceName"
        class="size-7 shrink-0 rounded-full object-cover"
    />
    <div class="ml-1 grid flex-1 text-left">
        <span class="truncate text-[13px] leading-tight font-medium">{{
            displayName
        }}</span>
    </div>
</template>
