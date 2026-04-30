<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

defineProps<{ open: boolean }>();
defineEmits<{ 'update:open': [value: boolean] }>();

type ShortcutGroup = {
    title: string;
    items: Array<{ keys: string[]; label: string }>;
};

const groups: ShortcutGroup[] = [
    {
        title: 'Navigation',
        items: [
            { keys: ['G', 'I'], label: 'Go to issues' },
            { keys: ['G', 'P'], label: 'Go to projects' },
            { keys: ['G', 'C'], label: 'Go to cycles' },
            { keys: ['G', 'H'], label: 'Go home' },
        ],
    },
    {
        title: 'Actions',
        items: [
            { keys: ['C'], label: 'Create new issue' },
            { keys: ['Ctrl', 'K'], label: 'Open search palette' },
            { keys: ['?'], label: 'Show keyboard shortcuts' },
        ],
    },
];
</script>

<template>
    <Dialog :open="open" @update:open="$emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Keyboard shortcuts</DialogTitle>
                <DialogDescription>
                    Move faster around the workspace.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-5">
                <section v-for="group in groups" :key="group.title">
                    <h3
                        class="mb-2 text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        {{ group.title }}
                    </h3>
                    <ul class="space-y-1.5">
                        <li
                            v-for="item in group.items"
                            :key="item.label"
                            class="flex items-center justify-between text-[13px]"
                        >
                            <span class="text-foreground">{{
                                item.label
                            }}</span>
                            <span class="flex items-center gap-1">
                                <kbd
                                    v-for="(k, idx) in item.keys"
                                    :key="idx"
                                    class="inline-flex min-w-[1.4rem] items-center justify-center rounded border border-border bg-card px-1.5 py-0.5 text-[11px] font-medium text-muted-foreground"
                                >
                                    {{ k }}
                                </kbd>
                            </span>
                        </li>
                    </ul>
                </section>
            </div>
        </DialogContent>
    </Dialog>
</template>
