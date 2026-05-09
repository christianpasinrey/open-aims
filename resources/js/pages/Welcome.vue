<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Github } from 'lucide-vue-next';
import ProjectIcon from '@/components/repo/ProjectIcon.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
</script>

<template>
    <Head title="AIMS" />

    <main
        class="relative flex min-h-svh w-full flex-col bg-background text-foreground antialiased"
    >
        <header class="flex items-center justify-between px-6 py-5 sm:px-10">
            <div class="flex items-center gap-2">
                <ProjectIcon :size="22" rounded="md" color="#6366f1" />
                <span class="text-[13px] font-medium">AIMS</span>
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
                IMS &middot; AIMS &middot; Advanced Issue Management System
            </span>

            <h1
                class="max-w-2xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl"
            >
                The way modern teams ship software.
            </h1>

            <p
                class="mt-4 max-w-xl text-[15px] text-balance text-muted-foreground"
            >
                An advanced issue management system for teams that move quickly
                and care about craft &mdash; issues, projects, cycles, and your
                repository, in one workspace.
            </p>

            <div class="mt-9 flex items-center gap-3">
                <Link
                    :href="'/login'"
                    class="inline-flex items-center gap-2 rounded-md bg-brand px-4 py-2 text-[13px] font-medium text-brand-foreground transition-colors hover:opacity-90"
                >
                    Sign in
                    <ArrowRight class="size-3.5" />
                </Link>
                <a
                    :href="'/gh/redirect'"
                    class="inline-flex items-center gap-2 rounded-md border border-border bg-card px-4 py-2 text-[13px] font-medium text-foreground transition-colors hover:bg-accent"
                >
                    <Github class="size-3.5" />
                    Continue with GitHub
                </a>
            </div>
        </section>

        <footer
            class="flex items-center justify-between px-6 py-5 text-[12px] text-muted-foreground sm:px-10"
        >
            <span
                >&copy; {{ new Date().getFullYear() }} AIMS &middot;
                AIMS</span
            >
            <span>Built with Laravel &middot; Inertia &middot; Vue</span>
        </footer>
    </main>
</template>
