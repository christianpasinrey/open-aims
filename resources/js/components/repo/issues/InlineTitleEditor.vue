<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
    identifier: string;
    title: string;
}>();

const editing = ref(false);
const local = ref(props.title);
const inputEl = ref<HTMLTextAreaElement | null>(null);

watch(
    () => props.title,
    (next) => {
        if (!editing.value) local.value = next;
    },
);

async function startEditing(): Promise<void> {
    editing.value = true;
    local.value = props.title;
    await nextTick();
    inputEl.value?.focus();
    inputEl.value?.setSelectionRange(local.value.length, local.value.length);
    autosize();
}

function cancel(): void {
    local.value = props.title;
    editing.value = false;
}

function save(): void {
    const next = local.value.trim();
    if (!next) {
        cancel();
        return;
    }
    if (next === props.title) {
        editing.value = false;
        return;
    }
    router.patch(
        `/issues/${props.identifier}`,
        { title: next },
        {
            preserveScroll: true,
            onSuccess: () => {
                editing.value = false;
            },
            onError: () => {
                editing.value = false;
            },
        },
    );
}

function onKeydown(e: KeyboardEvent): void {
    if (e.key === 'Escape') {
        e.preventDefault();
        cancel();
    } else if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        save();
    }
}

function autosize(): void {
    const el = inputEl.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = `${el.scrollHeight}px`;
}
</script>

<template>
    <div>
        <textarea
            v-if="editing"
            ref="inputEl"
            v-model="local"
            rows="1"
            class="w-full resize-none rounded-md border border-border bg-background px-2 py-1.5 text-[22px] font-semibold leading-tight tracking-tight text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
            @blur="save"
            @keydown="onKeydown"
            @input="autosize"
        ></textarea>
        <h1
            v-else
            class="cursor-text rounded-md px-2 py-1.5 text-[22px] font-semibold leading-tight tracking-tight text-foreground transition-colors hover:bg-accent/40 -mx-2"
            @click="startEditing"
        >
            {{ title }}
        </h1>
    </div>
</template>
