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
const initial = computed<string>(() =>
    workspaceName.value.charAt(0).toUpperCase(),
);
const color = computed<string>(() => workspace.value.color ?? '#6366f1');
const logoUrl = computed<string | null>(() => workspace.value.logo_url ?? null);
</script>

<template>
    <span
        v-if="!logoUrl"
        class="flex aspect-square size-7 shrink-0 items-center justify-center rounded-md text-[11px] font-semibold text-white uppercase"
        :style="{ backgroundColor: color }"
    >
        {{ initial }}
    </span>
    <img
        v-else
        :src="logoUrl"
        :alt="workspaceName"
        class="size-7 shrink-0 rounded-md object-cover"
    />
    <div class="ml-1 grid flex-1 text-left">
        <span class="truncate text-[13px] leading-tight font-medium">{{
            workspaceName
        }}</span>
    </div>
</template>
