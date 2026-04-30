<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Sign in to AIMS',
        description: 'Enter your email and password to continue.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Sign in" />

    <p
        v-if="status"
        class="mb-4 rounded-md border border-border bg-card px-3 py-2 text-center text-[13px] text-foreground"
    >
        {{ status }}
    </p>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-4"
    >
        <div class="flex flex-col gap-1.5">
            <label
                for="email"
                class="text-[12px] font-medium text-foreground"
            >
                Email
            </label>
            <input
                id="email"
                type="email"
                name="email"
                required
                autofocus
                autocomplete="email"
                placeholder="you@workspace.com"
                class="h-10 w-full rounded-md border border-border bg-card px-3 text-[14px] text-foreground placeholder:text-muted-foreground/60 focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
            />
            <p
                v-if="errors.email"
                class="text-[12px] text-destructive"
            >
                {{ errors.email }}
            </p>
        </div>

        <div class="flex flex-col gap-1.5">
            <div class="flex items-center justify-between">
                <label
                    for="password"
                    class="text-[12px] font-medium text-foreground"
                >
                    Password
                </label>
                <Link
                    v-if="canResetPassword"
                    :href="request()"
                    class="text-[12px] text-muted-foreground transition-colors hover:text-foreground"
                >
                    Forgot password?
                </Link>
            </div>
            <input
                id="password"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="••••••••"
                class="h-10 w-full rounded-md border border-border bg-card px-3 text-[14px] text-foreground placeholder:text-muted-foreground/60 focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/30"
            />
            <p
                v-if="errors.password"
                class="text-[12px] text-destructive"
            >
                {{ errors.password }}
            </p>
        </div>

        <label
            class="flex select-none items-center gap-2 text-[13px] text-muted-foreground"
        >
            <input
                type="checkbox"
                name="remember"
                class="size-3.5 rounded border-border accent-brand"
                data-test="remember-checkbox"
            />
            Keep me signed in
        </label>

        <button
            type="submit"
            :disabled="processing"
            data-test="login-button"
            class="mt-1 inline-flex h-10 items-center justify-center gap-2 rounded-md bg-brand text-[13px] font-medium text-brand-foreground transition-opacity hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-60"
        >
            <Loader2 v-if="processing" class="size-4 animate-spin" />
            <span>{{ processing ? 'Signing in…' : 'Sign in' }}</span>
        </button>
    </Form>

    <div class="my-5 flex items-center gap-3 text-[12px] text-muted-foreground">
        <span class="h-px flex-1 bg-border"></span>
        <span>or</span>
        <span class="h-px flex-1 bg-border"></span>
    </div>

    <a
        href="/gh/redirect?intent=login"
        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-md border border-border bg-card text-[13px] font-medium text-foreground transition-colors hover:bg-accent"
    >
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="currentColor"
            class="size-4"
            aria-hidden="true"
        >
            <path d="M12 .5C5.65.5.5 5.65.5 12c0 5.08 3.29 9.39 7.86 10.91.58.11.79-.25.79-.56v-2c-3.2.69-3.87-1.36-3.87-1.36-.52-1.32-1.27-1.67-1.27-1.67-1.04-.71.08-.7.08-.7 1.15.08 1.76 1.18 1.76 1.18 1.02 1.75 2.68 1.24 3.34.95.1-.74.4-1.24.73-1.53-2.55-.29-5.23-1.27-5.23-5.66 0-1.25.45-2.27 1.18-3.07-.12-.29-.51-1.46.11-3.04 0 0 .96-.31 3.15 1.17.91-.25 1.88-.38 2.85-.38.97 0 1.94.13 2.85.38 2.19-1.48 3.15-1.17 3.15-1.17.62 1.58.23 2.75.11 3.04.74.8 1.18 1.82 1.18 3.07 0 4.4-2.69 5.36-5.25 5.65.41.35.78 1.05.78 2.12v3.14c0 .31.21.68.8.56C20.21 21.39 23.5 17.08 23.5 12 23.5 5.65 18.35.5 12 .5z" />
        </svg>
        <span>Continue with GitHub</span>
    </a>
</template>
