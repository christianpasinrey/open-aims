<script setup lang="ts">
import KeyboardShortcutsDialog from '@/components/KeyboardShortcutsDialog.vue';
import InlineComposer from '@/components/repo/issues/InlineComposer.vue';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();

const { cheatSheetOpen, composerOpen, composerTeamKey, closeComposer } =
    useKeyboardShortcuts();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <slot />

        <KeyboardShortcutsDialog
            :open="cheatSheetOpen"
            @update:open="cheatSheetOpen = $event"
        />

        <div
            v-if="composerOpen && composerTeamKey"
            class="fixed inset-x-0 top-0 z-50 mx-auto max-w-2xl px-4 pt-4"
        >
            <div
                class="rounded-md border border-border bg-background shadow-lg"
            >
                <InlineComposer
                    :open="composerOpen"
                    :team-key="composerTeamKey"
                    @close="closeComposer"
                />
            </div>
        </div>
    </AppLayout>
</template>
