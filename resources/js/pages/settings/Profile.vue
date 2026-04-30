<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Profile settings" />

    <h1 class="sr-only">Profile settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profile information"
            description="Update your name and email address"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Full name"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="Email address"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div v-if="mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    Your email address is unverified.
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        Click here to resend the verification email.
                    </Link>
                </p>

                <div
                    v-if="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button"
                    >Save</Button
                >
            </div>
        </Form>
    </div>

    <section class="mt-10 flex flex-col space-y-3">
        <Heading
            variant="small"
            title="Connected accounts"
            description="Sign in with another provider or link an existing account."
        />

        <p
            v-if="(page.props.errors as Record<string, string>).github"
            class="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-[12.5px] text-destructive"
        >
            {{ (page.props.errors as Record<string, string>).github }}
        </p>

        <div
            class="flex items-center justify-between gap-4 rounded-md border border-border bg-card px-4 py-3"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex size-9 items-center justify-center rounded-md bg-muted text-foreground"
                    aria-hidden="true"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        class="size-5"
                    >
                        <path d="M12 .5C5.65.5.5 5.65.5 12c0 5.08 3.29 9.39 7.86 10.91.58.11.79-.25.79-.56v-2c-3.2.69-3.87-1.36-3.87-1.36-.52-1.32-1.27-1.67-1.27-1.67-1.04-.71.08-.7.08-.7 1.15.08 1.76 1.18 1.76 1.18 1.02 1.75 2.68 1.24 3.34.95.1-.74.4-1.24.73-1.53-2.55-.29-5.23-1.27-5.23-5.66 0-1.25.45-2.27 1.18-3.07-.12-.29-.51-1.46.11-3.04 0 0 .96-.31 3.15 1.17.91-.25 1.88-.38 2.85-.38.97 0 1.94.13 2.85.38 2.19-1.48 3.15-1.17 3.15-1.17.62 1.58.23 2.75.11 3.04.74.8 1.18 1.82 1.18 3.07 0 4.4-2.69 5.36-5.25 5.65.41.35.78 1.05.78 2.12v3.14c0 .31.21.68.8.56C20.21 21.39 23.5 17.08 23.5 12 23.5 5.65 18.35.5 12 .5z" />
                    </svg>
                </span>
                <div>
                    <div class="text-[13px] font-medium text-foreground">GitHub</div>
                    <div
                        v-if="user.github_login"
                        class="text-[12px] text-muted-foreground"
                    >
                        Linked as <span class="font-mono">@{{ user.github_login }}</span>
                    </div>
                    <div v-else class="text-[12px] text-muted-foreground">
                        Not connected
                    </div>
                </div>
            </div>
            <a
                v-if="!user.github_login"
                href="/gh/redirect?intent=connect"
                class="inline-flex h-8 items-center rounded-md bg-foreground px-3 text-[12px] font-medium text-background transition-opacity hover:opacity-90"
            >
                Connect
            </a>
            <Form
                v-else
                action="/gh/disconnect"
                method="delete"
                v-slot="{ processing: ghProcessing }"
            >
                <Button
                    type="submit"
                    variant="outline"
                    size="sm"
                    :disabled="ghProcessing"
                >
                    {{ ghProcessing ? 'Disconnecting…' : 'Disconnect' }}
                </Button>
            </Form>
        </div>
        <p
            v-if="status === 'github-linked'"
            class="text-[12px] text-emerald-500"
        >
            GitHub connected.
        </p>
        <p
            v-else-if="status === 'github-unlinked'"
            class="text-[12px] text-muted-foreground"
        >
            GitHub disconnected.
        </p>
    </section>

    <DeleteUser />
</template>
