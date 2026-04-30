<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Inbox } from 'lucide-vue-next';
</script>

<template>
    <Head title="Inbox" />

    <div class="flex h-full flex-1 flex-col overflow-hidden">
        <header class="flex shrink-0 items-center gap-2 border-b border-border px-5 py-3">
            <Inbox class="size-4 text-muted-foreground" />
            <h1 class="text-[13px] font-medium">Inbox</h1>
        </header>
        <div class="flex flex-1 items-center justify-center px-6 py-16 text-center">
            <div class="max-w-sm">
                <div class="mx-auto flex size-10 items-center justify-center rounded-full bg-muted">
                    <Inbox class="size-5 text-muted-foreground" />
                </div>
                <h2 class="mt-4 text-base font-medium">Your inbox is empty</h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Notifications about issues you're subscribed to will appear here.
                </p>
            </div>
        </div>
    </div>
</template>
