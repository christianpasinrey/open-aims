<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';

defineOptions({
    layout: {
        title: 'Aceptar invitación',
        description: '',
    },
});

const props = defineProps<{
    valid: boolean;
    token?: string;
    email?: string;
    workspaceName?: string;
    accountExists?: boolean;
}>();

const form = useForm<{
    name: string;
    password: string;
    password_confirmation: string;
    invitation?: string;
}>({
    name: '',
    password: '',
    password_confirmation: '',
});

function submit(): void {
    form.post(`/invite/${props.token}`);
}
</script>

<template>
    <Head title="Aceptar invitación" />

    <!-- State 1: invalid / expired invitation -->
    <template v-if="!valid">
        <div class="mb-6 rounded-md border border-destructive/30 bg-destructive/10 px-4 py-3 text-center text-[13px] text-destructive">
            Esta invitación no es válida o ha expirado.
        </div>
        <p class="mb-4 text-center text-[13px] text-muted-foreground">
            Pide a tu administrador que te envíe una nueva invitación.
        </p>
        <Link
            href="/login"
            class="mt-1 flex h-10 items-center justify-center rounded-md border border-border bg-card text-[13px] font-medium text-foreground transition-colors hover:bg-accent"
        >
            Ir al inicio de sesión
        </Link>
    </template>

    <!-- State 2: valid + account already exists -->
    <template v-else-if="accountExists">
        <p class="mb-1 text-center text-[13px] text-muted-foreground">
            Ya existe una cuenta para
            <span class="font-medium text-foreground">{{ email }}</span>.
        </p>
        <p class="mb-6 text-center text-[13px] text-muted-foreground">
            Inicia sesión con esa cuenta y abre de nuevo el enlace de invitación para unirte
            a <span class="font-medium text-foreground">{{ workspaceName }}</span>.
        </p>
        <Link
            href="/login"
            class="mt-1 flex h-10 items-center justify-center rounded-md bg-brand text-[13px] font-medium text-brand-foreground transition-opacity hover:opacity-90"
        >
            Iniciar sesión
        </Link>
    </template>

    <!-- State 3: valid + new account -->
    <template v-else>
        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="flex flex-col gap-1.5">
                <label for="name" class="text-[12px] font-medium text-foreground">
                    Nombre completo
                </label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    name="name"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Tu nombre"
                    class="h-10 w-full rounded-md border border-border bg-card px-3 text-[14px] text-foreground placeholder:text-muted-foreground/60 focus:border-ring focus:ring-2 focus:ring-ring/30 focus:outline-none"
                    :class="{ 'border-destructive focus:border-destructive focus:ring-destructive/30': form.errors.name }"
                />
                <p v-if="form.errors.name" class="text-[12px] text-destructive">
                    {{ form.errors.name }}
                </p>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="password" class="text-[12px] font-medium text-foreground">
                    Contraseña
                </label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="••••••••"
                    class="h-10 w-full rounded-md border border-border bg-card px-3 text-[14px] text-foreground placeholder:text-muted-foreground/60 focus:border-ring focus:ring-2 focus:ring-ring/30 focus:outline-none"
                    :class="{ 'border-destructive focus:border-destructive focus:ring-destructive/30': form.errors.password }"
                />
                <p v-if="form.errors.password" class="text-[12px] text-destructive">
                    {{ form.errors.password }}
                </p>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="password_confirmation" class="text-[12px] font-medium text-foreground">
                    Confirmar contraseña
                </label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="••••••••"
                    class="h-10 w-full rounded-md border border-border bg-card px-3 text-[14px] text-foreground placeholder:text-muted-foreground/60 focus:border-ring focus:ring-2 focus:ring-ring/30 focus:outline-none"
                />
            </div>

            <p v-if="form.errors.invitation" class="text-[12px] text-destructive">
                {{ form.errors.invitation }}
            </p>

            <button
                type="submit"
                :disabled="form.processing"
                class="mt-1 inline-flex h-10 items-center justify-center gap-2 rounded-md bg-brand text-[13px] font-medium text-brand-foreground transition-opacity hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-60"
            >
                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                <span>{{ form.processing ? 'Creando cuenta…' : 'Crear cuenta y unirme' }}</span>
            </button>
        </form>
    </template>
</template>
