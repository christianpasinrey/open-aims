<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { nextTick, ref, watch } from 'vue';
import MarkdownContent from '@/components/repo/MarkdownContent.vue';
import RichEditor from '@/components/repo/RichEditor.vue';

const props = defineProps<{
    identifier: string;
    description: string | null;
}>();

const editing = ref(false);
const saving = ref(false);
const local = ref(props.description ?? '');
const editorRef = ref<InstanceType<typeof RichEditor> | null>(null);

watch(
    () => props.description,
    (next) => {
        if (!editing.value) {
            local.value = next ?? '';
        }
    },
);

async function startEditing(): Promise<void> {
    editing.value = true;
    local.value = props.description ?? '';
    await nextTick();
    editorRef.value?.focus();
}

function cancel(): void {
    local.value = props.description ?? '';
    editing.value = false;
}

function save(): void {
    const next = local.value.trim();
    const original = (props.description ?? '').trim();
    if (next === original) {
        editing.value = false;
        return;
    }

    saving.value = true;
    router.patch(
        `/issues/${props.identifier}`,
        { description: next === '' ? null : local.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                editing.value = false;
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="relative">
        <template v-if="editing">
            <RichEditor
                ref="editorRef"
                v-model="local"
                placeholder="Add a description…"
                autofocus
                @blur="save"
                @submit="save"
                @cancel="cancel"
            />
            <div
                class="pointer-events-none mt-2 flex items-center gap-3 text-[11px] text-muted-foreground/80"
            >
                <span>
                    <kbd class="font-mono">Ctrl</kbd>+<kbd class="font-mono">Enter</kbd>
                    to save
                </span>
                <span>
                    <kbd class="font-mono">Esc</kbd>
                    to cancel
                </span>
                <span v-if="saving" class="ml-auto inline-flex items-center gap-1">
                    <span class="size-1.5 animate-pulse rounded-full bg-muted-foreground"></span>
                    Saving…
                </span>
            </div>
        </template>

        <MarkdownContent
            v-else-if="description"
            :source="description"
            :identifier="identifier"
            class="cursor-text rounded-md transition-colors hover:bg-accent/30"
            @click="startEditing"
        />
        <button
            v-else
            type="button"
            class="-mx-3 rounded-md px-3 py-2 text-[14px] text-muted-foreground italic transition-colors hover:bg-accent/40"
            @click="startEditing"
        >
            Add a description…
        </button>
    </div>
</template>
