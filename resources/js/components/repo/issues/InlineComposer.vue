<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
    open: boolean;
    teamKey: string;
    /** Extra fields injected based on the active group bucket. */
    context?: Record<string, string | number | null | undefined>;
}>();
const emit = defineEmits<{ close: [] }>();

const title = ref('');
const inputEl = ref<HTMLInputElement | null>(null);
const submitting = ref(false);

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            title.value = '';
            await nextTick();
            inputEl.value?.focus();
        }
    },
);

function close(): void {
    title.value = '';
    emit('close');
}

function submit(): void {
    const t = title.value.trim();
    if (!t || submitting.value) {
        if (!t) close();
        return;
    }
    submitting.value = true;
    const payload: Record<string, string | number | null> = {
        title: t,
        team_key: props.teamKey,
    };
    for (const [k, v] of Object.entries(props.context ?? {})) {
        if (v !== null && v !== undefined && v !== '') payload[k] = v as string | number;
    }
    router.post('/issues', payload, {
        preserveScroll: true,
        onFinish: () => {
            submitting.value = false;
        },
    });
}

function onBlur(): void {
    // Mirror repo: empty input dismisses on blur, populated input stays.
    if (!title.value.trim()) close();
}
</script>

<template>
    <div
        v-if="open"
        class="flex items-center gap-2 border-b border-border bg-background px-4 py-1.5"
    >
        <span class="size-3.5 shrink-0 rounded-full border border-dashed border-border"></span>
        <input
            ref="inputEl"
            v-model="title"
            type="text"
            placeholder="Issue title"
            class="flex-1 bg-transparent text-[13px] text-foreground placeholder:text-muted-foreground focus:outline-none"
            :disabled="submitting"
            @keydown.enter.prevent="submit"
            @keydown.esc.prevent="close"
            @blur="onBlur"
        />
        <kbd
            class="rounded border border-border bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
        >Enter</kbd>
    </div>
</template>
