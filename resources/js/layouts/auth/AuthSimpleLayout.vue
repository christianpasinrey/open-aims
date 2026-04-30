<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';
import ThemeToggle from '@/components/ThemeToggle.vue';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <main
        class="relative flex min-h-svh w-full flex-col bg-background text-foreground antialiased"
    >
        <header
            class="flex items-center justify-between px-6 py-5 sm:px-10"
        >
            <Link
                :href="home()"
                class="flex items-center gap-2 transition-opacity hover:opacity-80"
            >
                <span
                    class="flex size-7 items-center justify-center rounded-md bg-brand text-brand-foreground text-xs font-semibold"
                    >D</span
                >
                <span class="text-sm font-medium">AIMS</span>
            </Link>
            <ThemeToggle />
        </header>

        <section
            class="flex flex-1 items-center justify-center px-6 pb-16"
        >
            <div class="w-full max-w-[380px]">
                <div class="mb-8 text-center">
                    <h1
                        v-if="title"
                        class="text-2xl font-semibold tracking-tight"
                    >
                        {{ title }}
                    </h1>
                    <p
                        v-if="description"
                        class="mt-2 text-[14px] text-muted-foreground"
                    >
                        {{ description }}
                    </p>
                </div>

                <slot />
            </div>
        </section>
    </main>
</template>
