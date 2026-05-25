<script setup lang="ts">
import { BubbleMenu } from '@tiptap/extension-bubble-menu';
import { Link } from '@tiptap/extension-link';
import { Placeholder } from '@tiptap/extension-placeholder';
import { Table } from '@tiptap/extension-table';
import { TableCell } from '@tiptap/extension-table-cell';
import { TableHeader } from '@tiptap/extension-table-header';
import { TableRow } from '@tiptap/extension-table-row';
import { TaskItem } from '@tiptap/extension-task-item';
import { TaskList } from '@tiptap/extension-task-list';
import { StarterKit } from '@tiptap/starter-kit';
import { Editor, EditorContent } from '@tiptap/vue-3';
import { Markdown } from 'tiptap-markdown';
import {
    Bold,
    Code,
    Heading2,
    Heading3,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    ListTodo,
    Quote,
    Strikethrough,
} from 'lucide-vue-next';
import { onBeforeUnmount, ref, shallowRef, watch } from 'vue';

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
    autofocus?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'blur'): void;
    (e: 'submit'): void;
    (e: 'cancel'): void;
}>();

const bubbleEl = ref<HTMLElement | null>(null);
const editor = shallowRef<Editor | null>(null);

function buildEditor(): void {
    editor.value?.destroy();
    const ed = new Editor({
        autofocus: props.autofocus ?? false,
        content: props.modelValue ?? '',
        extensions: [
            StarterKit.configure({
                codeBlock: { HTMLAttributes: { class: 'tt-code-block' } },
                code: { HTMLAttributes: { class: 'tt-code' } },
                heading: { levels: [1, 2, 3] },
            }),
            Link.configure({
                openOnClick: false,
                autolink: true,
                HTMLAttributes: { class: 'tt-link' },
            }),
            Placeholder.configure({ placeholder: props.placeholder ?? 'Add a description…' }),
            TaskList,
            TaskItem.configure({ nested: true }),
            Table.configure({ resizable: true, HTMLAttributes: { class: 'tt-table' } }),
            TableRow,
            TableHeader,
            TableCell,
            BubbleMenu.configure({
                element: bubbleEl.value as HTMLElement,
                tippyOptions: { placement: 'top', duration: 100 },
            }),
            Markdown.configure({
                html: false,
                tightLists: true,
                linkify: true,
                breaks: true,
                transformPastedText: true,
                transformCopiedText: true,
            }),
        ],
        onUpdate: ({ editor: e }) => {
            // tiptap-markdown adds .storage.markdown.getMarkdown()
            const md = (e.storage as { markdown?: { getMarkdown: () => string } }).markdown?.getMarkdown() ?? '';
            emit('update:modelValue', md);
        },
        onBlur: () => emit('blur'),
        editorProps: {
            attributes: {
                class: 'tt-prose markdown-body focus:outline-none',
                spellcheck: 'false',
            },
            handleKeyDown: (_view, event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    emit('cancel');
                    return true;
                }
                if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
                    event.preventDefault();
                    emit('submit');
                    return true;
                }
                return false;
            },
        },
    });
    editor.value = ed;
}

watch(
    () => bubbleEl.value,
    (el) => {
        if (el && !editor.value) {
            buildEditor();
        }
    },
    { immediate: false },
);

watch(
    () => props.modelValue,
    (next) => {
        const ed = editor.value;
        if (!ed) {
            return;
        }
        const current = (ed.storage as { markdown?: { getMarkdown: () => string } }).markdown?.getMarkdown() ?? '';
        if ((next ?? '') !== current) {
            ed.commands.setContent(next ?? '', false);
        }
    },
);

onBeforeUnmount(() => {
    editor.value?.destroy();
    editor.value = null;
});

function setHeading(level: 1 | 2 | 3): void {
    editor.value?.chain().focus().toggleHeading({ level }).run();
}
function toggle(name: 'bold' | 'italic' | 'strike' | 'code'): void {
    const chain = editor.value?.chain().focus();
    if (!chain) return;
    if (name === 'bold') chain.toggleBold().run();
    else if (name === 'italic') chain.toggleItalic().run();
    else if (name === 'strike') chain.toggleStrike().run();
    else if (name === 'code') chain.toggleCode().run();
}
function bullet(): void {
    editor.value?.chain().focus().toggleBulletList().run();
}
function ordered(): void {
    editor.value?.chain().focus().toggleOrderedList().run();
}
function task(): void {
    editor.value?.chain().focus().toggleTaskList().run();
}
function blockquote(): void {
    editor.value?.chain().focus().toggleBlockquote().run();
}
function setLink(): void {
    const ed = editor.value;
    if (!ed) return;
    const previous = ed.getAttributes('link').href as string | undefined;
    const url = window.prompt('URL', previous ?? '');
    if (url === null) return;
    if (url === '') {
        ed.chain().focus().unsetLink().run();
        return;
    }
    ed.chain().focus().setLink({ href: url }).run();
}

function isActive(name: string, attrs?: Record<string, unknown>): boolean {
    return editor.value?.isActive(name, attrs as never) ?? false;
}

defineExpose({
    focus: () => editor.value?.commands.focus(),
});
</script>

<template>
    <div class="rich-editor">
        <!-- Floating bubble menu surfaces on selection -->
        <div ref="bubbleEl" class="tt-bubble">
            <button
                type="button"
                :class="['tt-bbtn', isActive('heading', { level: 2 }) && 'tt-bbtn-active']"
                title="Heading 2"
                @click="setHeading(2)"
            ><Heading2 class="size-3.5" /></button>
            <button
                type="button"
                :class="['tt-bbtn', isActive('heading', { level: 3 }) && 'tt-bbtn-active']"
                title="Heading 3"
                @click="setHeading(3)"
            ><Heading3 class="size-3.5" /></button>
            <span class="tt-divider"></span>
            <button
                type="button"
                :class="['tt-bbtn', isActive('bold') && 'tt-bbtn-active']"
                title="Bold ⌘B"
                @click="toggle('bold')"
            ><Bold class="size-3.5" /></button>
            <button
                type="button"
                :class="['tt-bbtn', isActive('italic') && 'tt-bbtn-active']"
                title="Italic ⌘I"
                @click="toggle('italic')"
            ><Italic class="size-3.5" /></button>
            <button
                type="button"
                :class="['tt-bbtn', isActive('strike') && 'tt-bbtn-active']"
                title="Strike"
                @click="toggle('strike')"
            ><Strikethrough class="size-3.5" /></button>
            <button
                type="button"
                :class="['tt-bbtn', isActive('code') && 'tt-bbtn-active']"
                title="Inline code"
                @click="toggle('code')"
            ><Code class="size-3.5" /></button>
            <span class="tt-divider"></span>
            <button
                type="button"
                :class="['tt-bbtn', isActive('link') && 'tt-bbtn-active']"
                title="Link"
                @click="setLink"
            ><LinkIcon class="size-3.5" /></button>
            <button
                type="button"
                :class="['tt-bbtn', isActive('blockquote') && 'tt-bbtn-active']"
                title="Quote"
                @click="blockquote"
            ><Quote class="size-3.5" /></button>
            <span class="tt-divider"></span>
            <button
                type="button"
                :class="['tt-bbtn', isActive('bulletList') && 'tt-bbtn-active']"
                title="Bullet list"
                @click="bullet"
            ><List class="size-3.5" /></button>
            <button
                type="button"
                :class="['tt-bbtn', isActive('orderedList') && 'tt-bbtn-active']"
                title="Ordered list"
                @click="ordered"
            ><ListOrdered class="size-3.5" /></button>
            <button
                type="button"
                :class="['tt-bbtn', isActive('taskList') && 'tt-bbtn-active']"
                title="Task list"
                @click="task"
            ><ListTodo class="size-3.5" /></button>
        </div>

        <EditorContent :editor="editor" />
    </div>
</template>

<style>
/* Bubble menu */
.tt-bubble {
    display: inline-flex;
    align-items: center;
    gap: 1px;
    padding: 4px;
    border-radius: 8px;
    background: var(--popover);
    border: 1px solid color-mix(in srgb, var(--border) 80%, transparent);
    box-shadow:
        0 4px 14px -2px rgb(0 0 0 / 0.4),
        0 2px 6px -2px rgb(0 0 0 / 0.3);
    color: var(--popover-foreground);
}
.tt-bbtn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 5px;
    color: var(--muted-foreground);
    transition:
        background 100ms ease,
        color 100ms ease;
}
.tt-bbtn:hover {
    background: var(--accent);
    color: var(--foreground);
}
.tt-bbtn-active {
    background: var(--accent);
    color: var(--foreground);
}
.tt-divider {
    display: inline-block;
    width: 1px;
    height: 16px;
    margin: 0 2px;
    background: var(--border);
}

/* TipTap inherits .markdown-body styles, so headings / code / tables look
   identical in editor vs read mode. A few editor-only tweaks: */
.ProseMirror {
    min-height: 1.5em;
    outline: none;
}
.ProseMirror p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: var(--muted-foreground);
    pointer-events: none;
    height: 0;
}
.ProseMirror ul[data-type='taskList'] {
    list-style: none;
    padding: 0;
}
.ProseMirror ul[data-type='taskList'] li {
    display: flex;
    align-items: flex-start;
    gap: 0.55em;
}
.ProseMirror ul[data-type='taskList'] li > label {
    margin-top: 0.32em;
}
.ProseMirror ul[data-type='taskList'] li > div {
    flex: 1;
    min-width: 0;
}
.ProseMirror table {
    border-collapse: collapse;
    table-layout: fixed;
    width: 100%;
}
.ProseMirror .selectedCell::after {
    content: '';
    position: absolute;
    inset: 0;
    background: color-mix(in srgb, var(--ring) 18%, transparent);
    pointer-events: none;
}
.ProseMirror .column-resize-handle {
    position: absolute;
    right: -2px;
    top: 0;
    bottom: 0;
    width: 4px;
    background: var(--ring);
    pointer-events: none;
}
</style>
