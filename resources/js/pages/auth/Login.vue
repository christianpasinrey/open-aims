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
</template>
