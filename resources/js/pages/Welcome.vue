<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login } from '@/routes';
</script>

<template>
    <Head title="repo-lab — IMS">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link
            rel="preconnect"
            href="https://fonts.gstatic.com"
            crossorigin
        />
        <link
            href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,500;9..144,700&family=IBM+Plex+Mono:wght@300;400;500&display=swap"
            rel="stylesheet"
        />
    </Head>

    <main
        class="relative min-h-svh w-full overflow-hidden bg-[#F4EFE6] text-[#1A1A1A] antialiased selection:bg-[#1A1A1A] selection:text-[#F4EFE6]"
    >
        <!-- Grain overlay -->
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 opacity-[0.07] mix-blend-multiply"
            style="
                background-image: url(&quot;data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E&quot;);
            "
        ></div>

        <!-- Inner frame: hairline border with crosshair corners -->
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-6 border border-[#1A1A1A]/15 sm:inset-8 lg:inset-12"
        ></div>

        <!-- Crosshair markers -->
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-6 sm:inset-8 lg:inset-12"
        >
            <span
                v-for="pos in [
                    'top-0 left-0',
                    'top-0 right-0',
                    'bottom-0 left-0',
                    'bottom-0 right-0',
                ]"
                :key="pos"
                :class="['absolute h-3 w-3', pos]"
            >
                <span
                    class="absolute top-1/2 left-0 h-px w-full -translate-y-1/2 bg-[#1A1A1A]"
                ></span>
                <span
                    class="absolute top-0 left-1/2 h-full w-px -translate-x-1/2 bg-[#1A1A1A]"
                ></span>
            </span>
        </div>

        <!-- Top monospace annotations -->
        <header
            class="absolute top-9 right-12 left-12 flex items-center justify-between text-[10px] tracking-[0.18em] text-[#1A1A1A]/70 uppercase sm:top-12 sm:right-14 sm:left-14 lg:top-16 lg:right-20 lg:left-20"
            style="font-family: 'IBM Plex Mono', ui-monospace, monospace"
        >
            <div class="flex items-center gap-3">
                <span
                    aria-hidden="true"
                    class="inline-block h-1.5 w-1.5 bg-[#B8431B]"
                ></span>
                <span>repo-lab&nbsp;/&nbsp;IMS</span>
                <span class="hidden text-[#1A1A1A]/40 sm:inline">v0.1.0</span>
            </div>
            <div class="hidden text-right sm:block">
                <span>Galicia · ES</span>
                <span class="ml-3 text-[#1A1A1A]/40">Est. 2026</span>
            </div>
        </header>

        <!-- Center composition -->
        <section
            class="relative z-10 flex min-h-svh flex-col items-center justify-center px-12 py-32 sm:px-14 lg:px-20"
        >
            <!-- Eyebrow with rule lines -->
            <div
                class="mb-10 flex w-full max-w-[640px] items-center gap-4 text-[10px] tracking-[0.32em] text-[#1A1A1A]/55 uppercase"
                style="font-family: 'IBM Plex Mono', ui-monospace, monospace"
            >
                <span class="h-px flex-1 bg-[#1A1A1A]/25"></span>
                <span>Issues Management System</span>
                <span class="h-px flex-1 bg-[#1A1A1A]/25"></span>
            </div>

            <!-- Display title -->
            <h1
                class="text-center text-5xl leading-[0.92] tracking-[-0.025em] text-[#1A1A1A] sm:text-7xl lg:text-[7.5rem]"
                style="
                    font-family: 'Fraunces', ui-serif, Georgia, serif;
                    font-weight: 400;
                    font-variation-settings: 'opsz' 144;
                "
            >
                repo<span class="text-[#B8431B]">·</span>lab
            </h1>

            <!-- Tagline -->
            <p
                class="mt-8 max-w-[56ch] text-balance text-center text-[13px] leading-relaxed text-[#1A1A1A]/65 sm:text-sm"
                style="font-family: 'IBM Plex Mono', ui-monospace, monospace"
            >
                Internal instrumentation for tracking research, engineering
                and clinical work across the lab.
            </p>

            <!-- CTA -->
            <div class="mt-14">
                <Link
                    v-if="$page.props.auth?.user"
                    :href="dashboard()"
                    class="group inline-flex items-center gap-3 border border-[#1A1A1A] bg-[#1A1A1A] px-8 py-4 text-[12px] tracking-[0.24em] text-[#F4EFE6] uppercase transition-colors duration-200 hover:bg-[#B8431B] hover:border-[#B8431B] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#B8431B] focus-visible:ring-offset-4 focus-visible:ring-offset-[#F4EFE6]"
                    style="
                        font-family:
                            'IBM Plex Mono', ui-monospace, monospace;
                    "
                >
                    Enter system
                    <span
                        aria-hidden="true"
                        class="transition-transform duration-200 group-hover:translate-x-1"
                        >→</span
                    >
                </Link>

                <Link
                    v-else
                    :href="login()"
                    class="group inline-flex items-center gap-3 border border-[#1A1A1A] bg-[#1A1A1A] px-8 py-4 text-[12px] tracking-[0.24em] text-[#F4EFE6] uppercase transition-colors duration-200 hover:bg-[#B8431B] hover:border-[#B8431B] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#B8431B] focus-visible:ring-offset-4 focus-visible:ring-offset-[#F4EFE6]"
                    style="
                        font-family:
                            'IBM Plex Mono', ui-monospace, monospace;
                    "
                >
                    Sign in
                    <span
                        aria-hidden="true"
                        class="transition-transform duration-200 group-hover:translate-x-1"
                        >→</span
                    >
                </Link>
            </div>

            <!-- Access notice -->
            <p
                class="mt-10 text-center text-[10px] tracking-[0.18em] text-[#1A1A1A]/45 uppercase"
                style="font-family: 'IBM Plex Mono', ui-monospace, monospace"
            >
                Access by invitation · Members only
            </p>
        </section>

        <!-- Footer annotations -->
        <footer
            class="absolute right-12 bottom-9 left-12 flex items-end justify-between gap-6 text-[10px] tracking-[0.18em] text-[#1A1A1A]/55 uppercase sm:right-14 sm:bottom-12 sm:left-14 lg:right-20 lg:bottom-16 lg:left-20"
            style="font-family: 'IBM Plex Mono', ui-monospace, monospace"
        >
            <div>[001 / 001]</div>
            <div class="hidden flex-1 items-center justify-center gap-1.5 sm:flex">
                <span
                    v-for="i in 24"
                    :key="i"
                    aria-hidden="true"
                    :class="[
                        'block w-px',
                        i % 4 === 0 ? 'h-2.5' : 'h-1.5',
                        'bg-[#1A1A1A]/30',
                    ]"
                ></span>
            </div>
            <div>Internal · No-fee</div>
        </footer>
    </main>
</template>
