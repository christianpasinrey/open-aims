<script setup lang="ts">
import { Loader2, Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import { useTeams, type CreateTeamPayload } from '@/composables/useTeams';

const { create } = useTeams();

const name = ref('');
const key = ref('');
const color = ref('');
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const PRESET_COLORS = [
    '#6366f1', '#3b82f6', '#10b981', '#f59e0b',
    '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6',
];

function submit(): void {
    if (!name.value.trim()) return;
    processing.value = true;
    errors.value = {};

    const payload: CreateTeamPayload = { name: name.value.trim() };
    if (key.value.trim()) payload.key = key.value.trim();
    if (color.value) payload.color = color.value;

    create(payload, (errs) => {
        errors.value = errs;
        processing.value = false;
    });
}
</script>

<template>
    <div class="rounded-md border border-border bg-card p-4">
        <p class="mb-3 text-[12px] font-medium uppercase tracking-wide text-muted-foreground">
            Nuevo equipo
        </p>

        <form class="flex flex-col gap-3" @submit.prevent="submit">
            <!-- Name -->
            <div class="flex flex-col gap-1">
                <label for="ct-name" class="text-[12px] font-medium text-foreground">
                    Nombre <span class="text-destructive">*</span>
                </label>
                <input
                    id="ct-name"
                    v-model="name"
                    type="text"
                    required
                    autocomplete="off"
                    placeholder="Nombre del equipo"
                    class="h-9 w-full rounded-md border border-border bg-background px-3 text-[13px] text-foreground placeholder:text-muted-foreground/60 focus:border-ring focus:ring-2 focus:ring-ring/30 focus:outline-none"
                    :class="{
                        'border-destructive focus:border-destructive focus:ring-destructive/30':
                            errors.name,
                    }"
                />
                <p v-if="errors.name" class="text-[12px] text-destructive">{{ errors.name }}</p>
            </div>

            <!-- Key -->
            <div class="flex flex-col gap-1">
                <label for="ct-key" class="text-[12px] font-medium text-foreground">
                    Key <span class="text-muted-foreground">(opcional)</span>
                </label>
                <input
                    id="ct-key"
                    v-model="key"
                    type="text"
                    autocomplete="off"
                    placeholder="EJ: LAM"
                    class="h-9 w-full rounded-md border border-border bg-background px-3 text-[13px] text-foreground placeholder:text-muted-foreground/60 focus:border-ring focus:ring-2 focus:ring-ring/30 focus:outline-none"
                    :class="{
                        'border-destructive focus:border-destructive focus:ring-destructive/30':
                            errors.key,
                    }"
                />
                <p v-if="errors.key" class="text-[12px] text-destructive">{{ errors.key }}</p>
            </div>

            <!-- Color -->
            <div class="flex flex-col gap-1.5">
                <span class="text-[12px] font-medium text-foreground">
                    Color <span class="text-muted-foreground">(opcional)</span>
                </span>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="c in PRESET_COLORS"
                        :key="c"
                        type="button"
                        :title="c"
                        class="size-6 rounded-full ring-offset-2 transition-all focus:outline-none"
                        :class="color === c ? 'ring-2 ring-foreground' : 'hover:scale-110'"
                        :style="{ backgroundColor: c }"
                        @click="color = color === c ? '' : c"
                    />
                </div>
            </div>

            <button
                type="submit"
                :disabled="processing || !name.trim()"
                class="inline-flex h-9 items-center justify-center gap-1.5 rounded-md bg-brand px-4 text-[13px] font-medium text-brand-foreground transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <Loader2 v-if="processing" class="size-3.5 animate-spin" />
                <Plus v-else class="size-3.5" />
                {{ processing ? 'Creando&#x2026;' : 'Crear equipo' }}
            </button>
        </form>
    </div>
</template>
