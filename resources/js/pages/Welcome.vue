<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import ThemeToggle from '@/components/ThemeToggle.vue';
</script>

<template>
    <Head title="AIMS" />

    <main
        class="relative flex min-h-svh w-full flex-col bg-background text-foreground antialiased"
    >
        <header
            class="flex items-center justify-between px-6 py-5 sm:px-10"
        >
            <div class="flex items-center gap-2">
                <span
                    class="flex size-7 items-center justify-center rounded-md bg-brand text-brand-foreground text-xs font-semibold"
                    >D</span
                >
                <span class="text-sm font-medium">AIMS</span>
            </div>
            <div class="flex items-center gap-2">
                <ThemeToggle />
                <Link
                    :href="'/login'"
                    class="rounded-md px-3 py-1.5 text-[13px] font-medium text-foreground transition-colors hover:bg-accent"
                    >Sign in</Link
                >
            </div>
        </header>

        <section
            class="flex flex-1 flex-col items-center justify-center px-6 pb-24 text-center"
        >
            <span
                class="mb-6 inline-flex items-center gap-2 rounded-full border border-border bg-card px-3 py-1 text-[12px] text-muted-foreground"
            >
                <span class="size-1.5 rounded-full bg-brand"></span>
                Issue tracking, simplified
            </span>

            <h1
                class="max-w-2xl text-balance text-4xl font-semibold tracking-tight sm:text-5xl"
            >
                The way modern teams ship software.
            </h1>

            <p
                class="mt-4 max-w-xl text-balance text-[15px] text-muted-foreground"
            >
                AIMS is a fast, opinionated workspace for tracking
                issues, projects, and cycles — built for teams that move
                quickly.
            </p>

            <div class="mt-9 flex items-center gap-3">
                <Link
                    :href="'/login'"
                    class="inline-flex items-center gap-2 rounded-md bg-brand px-4 py-2.5 text-[13px] font-medium text-brand-foreground transition-colors hover:opacity-90"
                >
                    Sign in
                    <ArrowRight class="size-4" />
                </Link>
                <a
                    href="https://the app"
                    target="_blank"
                    rel="noreferrer"
                    class="rounded-md px-4 py-2.5 text-[13px] font-medium text-muted-foreground transition-colors hover:text-foreground"
                    >Inspired by repo ↗</a
                >
            </div>
        </section>

        <footer
            class="flex items-center justify-between px-6 py-5 text-[12px] text-muted-foreground sm:px-10"
        >
            <span>© {{ new Date().getFullYear() }} AIMS</span>
            <span>Built with Laravel · Inertia · Vue</span>
        </footer>
    </main>
</template>
