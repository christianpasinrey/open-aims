<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import IssueRefHoverCard from '@/components/repo/IssueRefHoverCard.vue';
import { renderMarkdown } from '@/lib/markdown';

const props = defineProps<{
    /** Raw markdown source. */
    source: string | null | undefined;
    /** When provided, task-list checkboxes become interactive and persist
     *  back to /issues/{identifier} via PATCH on the description field. */
    identifier?: string | null;
    /** Set false to skip enabling task-list checkbox interactivity. */
    interactiveTasks?: boolean;
    /** Optional extra class for the rendered root container. */
    class?: string;
}>();

type Segment = { kind: 'html'; html: string } | { kind: 'ref'; identifier: string };

const segments = computed<Segment[]>(() => {
    const html = renderMarkdown(props.source ?? null);
    if (!html) {
        return [];
    }

    const out: Segment[] = [];
    let lastIndex = 0;
    let match: RegExpExecArray | null;
    const re = /<<ISSUE-REF:([A-Z][A-Z0-9]*-\d+)>>/g;

    while ((match = re.exec(html)) !== null) {
        if (match.index > lastIndex) {
            out.push({ kind: 'html', html: html.slice(lastIndex, match.index) });
        }
        out.push({ kind: 'ref', identifier: match[1] });
        lastIndex = match.index + match[0].length;
    }
    if (lastIndex < html.length) {
        out.push({ kind: 'html', html: html.slice(lastIndex) });
    }
    return out;
});

/**
 * Replace the Nth `[ ]` / `[x]` task token in the original source.
 * Counts only tokens at the start of list items.
 */
function toggleTaskAt(source: string, taskIndex: number, nextChecked: boolean): string {
    const lineRe = /^([ \t]*[-*+]\s+)\[( |x|X)\]/gm;
    let n = 0;
    return source.replace(lineRe, (whole, prefix) => {
        const replaced = n === taskIndex
            ? `${prefix}[${nextChecked ? 'x' : ' '}]`
            : whole;
        n++;
        return replaced;
    });
}

function onClick(event: MouseEvent): void {
    const target = event.target as HTMLElement | null;
    if (!target) {
        return;
    }
    if (target.tagName !== 'INPUT' || (target as HTMLInputElement).type !== 'checkbox') {
        return;
    }
    if (!props.identifier || props.interactiveTasks === false) {
        return;
    }

    const root = event.currentTarget as HTMLElement;
    const checkboxes = Array.from(
        root.querySelectorAll('input[type="checkbox"]'),
    ) as HTMLInputElement[];
    const idx = checkboxes.indexOf(target as HTMLInputElement);
    if (idx < 0) {
        return;
    }

    event.preventDefault();
    const nextChecked = !((target as HTMLInputElement).defaultChecked);
    const nextSource = toggleTaskAt(props.source ?? '', idx, nextChecked);
    if (nextSource === (props.source ?? '')) {
        return;
    }

    router.patch(
        `/issues/${props.identifier}`,
        { description: nextSource },
        { preserveScroll: true, preserveState: true, only: [] },
    );
}
</script>

<template>
    <div
        :class="['markdown-body', $props.class]"
        @click="onClick"
    >
        <template v-for="(seg, i) in segments" :key="i">
            <span v-if="seg.kind === 'html'" v-html="seg.html"></span>
            <IssueRefHoverCard v-else :identifier="seg.identifier" />
        </template>
    </div>
</template>
