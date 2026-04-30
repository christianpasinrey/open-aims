<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { renderMarkdown } from '@/lib/markdown';

const props = defineProps<{
    identifier: string;
    description: string | null;
}>();

const editing = ref(false);
const local = ref(props.description ?? '');
const textareaEl = ref<HTMLTextAreaElement | null>(null);

const html = computed<string>(() => renderMarkdown(props.description));

watch(
    () => props.description,
    (next) => {
        if (!editing.value) local.value = next ?? '';
    },
);

async function startEditing(): Promise<void> {
    editing.value = true;
    local.value = props.description ?? '';
    await nextTick();
    textareaEl.value?.focus();
    autosize();
}

function cancel(): void {
    local.value = props.description ?? '';
    editing.value = false;
}

function save(): void {
    const next = local.value;
    if (next === (props.description ?? '')) {
        editing.value = false;
        return;
    }
    router.patch(
        `/issues/${props.identifier}`,
        { description: next === '' ? null : next },
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
    } else if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        save();
    }
}

function autosize(): void {
    const el = textareaEl.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = `${Math.max(el.scrollHeight, 120)}px`;
}
</script>

<template>
    <div>
        <textarea
            v-if="editing"
            ref="textareaEl"
            v-model="local"
            placeholder="Add a description…"
            class="w-full resize-none rounded-md border border-border bg-background px-3 py-2 text-[14px] text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
            @blur="save"
            @keydown="onKeydown"
            @input="autosize"
        ></textarea>
        <div
            v-else-if="html"
            class="markdown-body cursor-text rounded-md px-3 py-2 transition-colors hover:bg-accent/40 -mx-3"
            v-html="html"
            @click="startEditing"
        ></div>
        <button
            v-else
            type="button"
            class="rounded-md px-3 py-2 text-[14px] italic text-muted-foreground transition-colors hover:bg-accent/40 -mx-3"
            @click="startEditing"
        >
            Add a description…
        </button>
    </div>
</template>
