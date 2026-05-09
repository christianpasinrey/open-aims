<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';

type Team = {
    id: number;
    name: string;
    key: string;
    icon: string | null;
    color: string | null;
    description: string | null;
    private: boolean;
    github_repo_full_name: string | null;
};

const props = defineProps<{ team: Team }>();

const name = ref<string>(props.team.name);
const color = ref<string>(props.team.color ?? '#6366f1');
const icon = ref<string>(props.team.icon ?? '');
const description = ref<string>(props.team.description ?? '');
const githubRepo = ref<string>(props.team.github_repo_full_name ?? '');
const submitting = ref(false);
const errorMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);

watch(
    () => props.team,
    (next) => {
        name.value = next.name;
        color.value = next.color ?? '#6366f1';
        icon.value = next.icon ?? '';
        description.value = next.description ?? '';
        githubRepo.value = next.github_repo_full_name ?? '';
    },
);

function save() {
    if (submitting.value) {
        return;
    }

    submitting.value = true;
    errorMessage.value = null;
    successMessage.value = null;
    router.patch(
        `/teams/${props.team.key}`,
        {
            name: name.value,
            color: color.value,
            icon: icon.value || null,
            description: description.value || null,
            github_repo_full_name: githubRepo.value.trim() || null,
        },
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
    <Head :title="`${team.name} · Settings`" />

    <div class="flex h-full flex-1 flex-col overflow-hidden">
        <header
            class="flex shrink-0 items-center gap-3 border-b border-border px-5 py-3"
        >
            <span
                class="flex size-6 items-center justify-center rounded-md text-[10px] font-semibold text-white uppercase"
                :style="{ backgroundColor: team.color || '#6366f1' }"
            >
                {{ team.key.charAt(0) }}
            </span>
            <h1 class="text-[13px] font-medium">{{ team.name }}</h1>
            <span class="text-[12px] text-muted-foreground uppercase"
                >Settings</span
            >
            <Link
                :href="`/teams/${team.key}/labels`"
                class="ml-auto rounded-md px-2.5 py-1 text-[12.5px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
            >
                Labels
            </Link>
            <Link
                :href="`/teams/${team.key}/members`"
                class="rounded-md px-2.5 py-1 text-[12.5px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
            >
                Members
            </Link>
        </header>

        <div class="flex-1 overflow-y-auto px-6 py-6">
            <div class="mx-auto max-w-xl space-y-10">
                <Heading
                    variant="small"
                    title="General"
                    description="Name, key, color, and icon."
                />
                <form class="space-y-6" @submit.prevent="save">
                    <div class="grid gap-2">
                        <Label for="t-name">Team name</Label>
                        <Input
                            id="t-name"
                            v-model="name"
                            required
                            maxlength="80"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="t-key">Team key</Label>
                        <Input
                            id="t-key"
                            :model-value="team.key"
                            readonly
                            class="font-mono text-[12.5px] text-muted-foreground"
                        />
                        <p class="text-[12px] text-muted-foreground">
                            Issue identifiers use this key (e.g.
                            <span class="font-mono">{{ team.key }}-123</span>);
                            it cannot be changed.
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="t-color">Color</Label>
                        <div class="flex items-center gap-3">
                            <input
                                id="t-color"
                                v-model="color"
                                type="color"
                                class="h-9 w-12 cursor-pointer rounded-md border border-input bg-transparent"
                            />
                            <Input
                                :model-value="color"
                                @update:model-value="color = String($event)"
                                maxlength="9"
                                class="font-mono text-[12.5px]"
                            />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="t-icon">Icon</Label>
                        <Input
                            id="t-icon"
                            v-model="icon"
                            placeholder="emoji or short code"
                            maxlength="32"
                        />
                        <p class="text-[12px] text-muted-foreground">
                            Optional emoji shown next to the team in some
                            surfaces.
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="t-desc">Description</Label>
                        <textarea
                            id="t-desc"
                            v-model="description"
                            rows="3"
                            maxlength="500"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-[13px] outline-none focus-visible:border-ring"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="t-repo">GitHub repository</Label>
                        <Input
                            id="t-repo"
                            v-model="githubRepo"
                            placeholder="owner/repo"
                            maxlength="200"
                            class="font-mono text-[12.5px]"
                        />
                        <p class="text-[12px] text-muted-foreground">
                            Branches and PRs from this repo are linked to issues
                            in this team. Use the
                            <span class="font-mono">owner/repo</span> form (e.g.
                            <span class="font-mono">acme/web</span>). Leave
                            empty to disable the link.
                        </p>
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
                    title="Danger zone"
                    description="Irreversible actions for this team."
                />
                <div
                    class="rounded-md border border-destructive/30 bg-destructive/5 p-4"
                >
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-[13px] font-medium">Archive team</p>
                            <p class="text-[12px] text-muted-foreground">
                                Hides the team from sidebars and search.
                            </p>
                        </div>
                        <Button variant="destructive" disabled>Archive</Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
