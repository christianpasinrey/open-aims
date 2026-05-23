<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { defineAsyncComponent } from 'vue';

const WorkspaceJoinSearch = defineAsyncComponent(
    () => import('@/components/workspace/WorkspaceJoinSearch.vue'),
);

defineOptions({
    layout: {
        title: 'Configura tu workspace',
        description: 'Crea tu primer workspace o únete a uno.',
    },
});

const form = useForm<{
    name: string;
    join_policy: 'request' | 'open' | 'private';
    team_name: string;
    team_key: string;
}>({
    name: '',
    join_policy: 'request',
    team_name: '',
    team_key: '',
});

function submit(): void {
    form.post('/workspaces');
}
</script>

<template>
    <Head title="Configura tu workspace" />

    <!-- Create workspace form -->
    <form class="flex flex-col gap-4" @submit.prevent="submit">
        <div class="flex flex-col gap-1.5">
            <label for="ws-name" class="text-[12px] font-medium text-foreground">
                Nombre del workspace
            </label>
            <input
                id="ws-name"
                v-model="form.name"
                type="text"
                name="name"
                required
                autofocus
                autocomplete="organization"
                placeholder="Mi empresa"
                class="h-10 w-full rounded-md border border-border bg-card px-3 text-[14px] text-foreground placeholder:text-muted-foreground/60 focus:border-ring focus:ring-2 focus:ring-ring/30 focus:outline-none"
                :class="{
                    'border-destructive focus:border-destructive focus:ring-destructive/30':
                        form.errors.name,
                }"
            />
            <p v-if="form.errors.name" class="text-[12px] text-destructive">
                {{ form.errors.name }}
            </p>
        </div>

        <div class="flex flex-col gap-1.5">
            <label
                for="ws-join-policy"
                class="text-[12px] font-medium text-foreground"
            >
                Quién puede unirse
            </label>
            <select
                id="ws-join-policy"
                v-model="form.join_policy"
                name="join_policy"
                class="h-10 w-full rounded-md border border-border bg-card px-3 text-[14px] text-foreground focus:border-ring focus:ring-2 focus:ring-ring/30 focus:outline-none"
                :class="{
                    'border-destructive focus:border-destructive focus:ring-destructive/30':
                        form.errors.join_policy,
                }"
            >
                <option value="request">
                    Por solicitud (lo aprueba un admin)
                </option>
                <option value="open">Abierto (cualquiera puede unirse)</option>
                <option value="private">
                    Privado (solo por invitación)
                </option>
            </select>
            <p
                v-if="form.errors.join_policy"
                class="text-[12px] text-destructive"
            >
                {{ form.errors.join_policy }}
            </p>
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="ws-team-name" class="text-[12px] font-medium text-foreground">
                Nombre del equipo
            </label>
            <input
                id="ws-team-name"
                v-model="form.team_name"
                type="text"
                name="team_name"
                autocomplete="off"
                :placeholder="form.name || 'Mi equipo'"
                class="h-10 w-full rounded-md border border-border bg-card px-3 text-[14px] text-foreground placeholder:text-muted-foreground/60 focus:border-ring focus:ring-2 focus:ring-ring/30 focus:outline-none"
                :class="{
                    'border-destructive focus:border-destructive focus:ring-destructive/30':
                        form.errors.team_name,
                }"
            />
            <p v-if="form.errors.team_name" class="text-[12px] text-destructive">
                {{ form.errors.team_name }}
            </p>
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="ws-team-key" class="text-[12px] font-medium text-foreground">
                Key del equipo <span class="text-muted-foreground">(opcional)</span>
            </label>
            <input
                id="ws-team-key"
                v-model="form.team_key"
                type="text"
                name="team_key"
                autocomplete="off"
                placeholder="EJ: LAM"
                class="h-10 w-full rounded-md border border-border bg-card px-3 text-[14px] text-foreground placeholder:text-muted-foreground/60 focus:border-ring focus:ring-2 focus:ring-ring/30 focus:outline-none"
                :class="{
                    'border-destructive focus:border-destructive focus:ring-destructive/30':
                        form.errors.team_key,
                }"
            />
            <p v-if="form.errors.team_key" class="text-[12px] text-destructive">
                {{ form.errors.team_key }}
            </p>
        </div>

        <button
            type="submit"
            :disabled="form.processing"
            class="mt-1 inline-flex h-10 items-center justify-center gap-2 rounded-md bg-brand text-[13px] font-medium text-brand-foreground transition-opacity hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-60"
        >
            <Loader2 v-if="form.processing" class="size-4 animate-spin" />
            <span>{{
                form.processing ? 'Creando workspace…' : 'Crear workspace'
            }}</span>
        </button>
    </form>

    <!-- Join existing workspace -->
    <div class="my-5 flex items-center gap-3 text-[12px] text-muted-foreground">
        <span class="h-px flex-1 bg-border"></span>
        <span>o</span>
        <span class="h-px flex-1 bg-border"></span>
    </div>

    <p class="mb-3 text-[13px] font-medium text-foreground">
        Unirse a un workspace existente
    </p>

    <WorkspaceJoinSearch />
</template>
