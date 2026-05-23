<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';

type Workspace = {
    id: number;
    name: string;
    slug: string;
    color: string;
    logo_url: string | null;
    telegram: { enabled: boolean; chat_id: string | null } | null;
    current_role: string | null;
};

const props = defineProps<{ ws: Workspace | null }>();

const sidebarNavItems = [
    { title: 'General', href: '/workspace/settings' },
    { title: 'Members', href: '/workspace/members' },
    { title: 'GitHub', href: '/workspace/github' },
];

const name = ref<string>(props.ws?.name ?? '');
const color = ref<string>(props.ws?.color ?? '#6366f1');
const submitting = ref(false);
const errorMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);

const telegramEnabled = ref<boolean>(props.ws?.telegram?.enabled ?? false);
const telegramChatId = ref<string>(props.ws?.telegram?.chat_id ?? '');

watch(
    () => props.ws,
    (next) => {
        name.value = next?.name ?? '';
        color.value = next?.color ?? '#6366f1';
        telegramEnabled.value = next?.telegram?.enabled ?? false;
        telegramChatId.value = next?.telegram?.chat_id ?? '';
    },
);

const slug = computed(() => props.ws?.slug ?? '');
const canManage = computed(() =>
    props.ws?.current_role === 'owner' || props.ws?.current_role === 'admin',
);

function save() {
    if (!props.ws || submitting.value) {
        return;
    }

    submitting.value = true;
    errorMessage.value = null;
    successMessage.value = null;
    router.patch(
        `/workspace/${props.ws.slug}`,
        { name: name.value, color: color.value },
        {
            preserveScroll: true,
            onFinish: () => {
                submitting.value = false;
            },
            onSuccess: () => {
                successMessage.value = 'Saved.';
            },
            onError: (errors) => {
                errorMessage.value =
                    Object.values(errors)[0] ?? 'Could not save.';
            },
        },
    );
}
</script>

<template>
    <Head title="Workspace settings" />

    <div class="px-4 py-6">
        <Heading
            title="Workspace settings"
            description="Manage your workspace and how it appears to teammates."
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav
                    class="flex flex-col space-y-1"
                    aria-label="Workspace settings"
                >
                    <Link
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-md px-3 py-2 text-[13px] font-medium transition-colors"
                        :class="
                            item.href === '/workspace/settings'
                                ? 'bg-muted text-foreground'
                                : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'
                        "
                    >
                        {{ item.title }}
                    </Link>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <div v-if="!ws" class="text-sm text-muted-foreground">
                        No active workspace.
                    </div>
                    <div v-else class="flex flex-col space-y-6">
                        <Heading
                            variant="small"
                            title="General"
                            description="Workspace name and identity."
                        />
                        <form class="space-y-6" @submit.prevent="save">
                            <div class="grid gap-2">
                                <Label for="ws-name">Workspace name</Label>
                                <Input
                                    id="ws-name"
                                    v-model="name"
                                    name="name"
                                    required
                                    maxlength="60"
                                    autocomplete="organization"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="ws-slug">URL slug</Label>
                                <Input
                                    id="ws-slug"
                                    :model-value="slug"
                                    name="slug"
                                    readonly
                                    class="font-mono text-[12.5px] text-muted-foreground"
                                />
                                <p class="text-[12px] text-muted-foreground">
                                    The slug appears in URLs and cannot be
                                    changed.
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="ws-color">Accent color</Label>
                                <div class="flex items-center gap-3">
                                    <input
                                        id="ws-color"
                                        v-model="color"
                                        type="color"
                                        class="h-9 w-12 cursor-pointer rounded-md border border-input bg-transparent"
                                    />
                                    <Input
                                        :model-value="color"
                                        @update:model-value="
                                            color = String($event)
                                        "
                                        name="color"
                                        maxlength="9"
                                        class="font-mono text-[12.5px]"
                                    />
                                </div>
                            </div>

                            <p
                                v-if="errorMessage"
                                class="text-[12.5px] text-destructive"
                            >
                                {{ errorMessage }}
                            </p>
                            <p
                                v-if="successMessage"
                                class="text-[12.5px] text-green-600"
                            >
                                {{ successMessage }}
                            </p>

                            <div class="flex items-center gap-4">
                                <Button :disabled="submitting" type="submit"
                                    >Save</Button
                                >
                            </div>
                        </form>

                        <Separator />

                        <Heading
                            variant="small"
                            title="Logo"
                            description="A small image used in place of the workspace initial."
                        />
                        <div class="grid gap-2">
                            <input
                                type="file"
                                accept="image/*"
                                disabled
                                class="block w-full rounded-md border border-input bg-transparent px-3 py-2 text-[12.5px] text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1 file:text-[12.5px] file:text-muted-foreground"
                            />
                            <p class="text-[12px] text-muted-foreground">
                                Uploads aren&rsquo;t wired up yet. Coming soon.
                            </p>
                        </div>

                        <template v-if="canManage">
                            <Separator />

                            <Heading
                                variant="small"
                                title="Telegram"
                                description="Publicación de actividad del workspace en Telegram."
                            />
                            <div class="space-y-6">
                                <div class="grid gap-2">
                                    <div class="flex items-center gap-3">
                                        <input
                                            id="tg-enabled"
                                            v-model="telegramEnabled"
                                            type="checkbox"
                                            class="h-4 w-4 cursor-pointer rounded border border-input accent-primary"
                                        />
                                        <Label for="tg-enabled" class="cursor-pointer">
                                            Enviar la actividad de este workspace a Telegram
                                        </Label>
                                    </div>
                                    <p class="text-[12px] text-muted-foreground">
                                        Si está desactivado, la actividad de este workspace no se publica en el canal de Telegram.
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="tg-chat-id">Chat ID</Label>
                                    <Input
                                        id="tg-chat-id"
                                        v-model="telegramChatId"
                                        name="telegram_chat_id"
                                        placeholder="–100123456789"
                                        class="font-mono text-[12.5px]"
                                    />
                                    <p class="text-[12px] text-muted-foreground">
                                        Déjalo vacío para usar el canal por defecto.
                                    </p>
                                </div>

                                <div class="flex items-center gap-4">
                                    <Button
                                        type="button"
                                        @click="
                                            router.patch(
                                                `/workspace/${ws!.slug}`,
                                                {
                                                    telegram_enabled: telegramEnabled,
                                                    telegram_chat_id: telegramChatId,
                                                },
                                                { preserveScroll: true },
                                            )
                                        "
                                    >
                                        Guardar
                                    </Button>
                                </div>
                            </div>
                        </template>

                        <Separator />

                        <Heading
                            variant="small"
                            title="Danger zone"
                            description="Irreversible actions for this workspace."
                        />
                        <div
                            class="rounded-md border border-destructive/30 bg-destructive/5 p-4"
                        >
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <div>
                                    <p class="text-[13px] font-medium">
                                        Delete workspace
                                    </p>
                                    <p
                                        class="text-[12px] text-muted-foreground"
                                    >
                                        Permanently removes the workspace and
                                        all of its data.
                                    </p>
                                </div>
                                <Button variant="destructive" disabled
                                    >Delete</Button
                                >
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
