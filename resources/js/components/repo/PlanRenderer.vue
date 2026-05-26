<script setup lang="ts">
import { computed, defineAsyncComponent, ref } from 'vue';

const MarkdownContent = defineAsyncComponent(
    () => import('@/components/repo/MarkdownContent.vue'),
);

const props = defineProps<{
    format: 'md' | 'html';
    content: string | null;
    libs?: string[] | null;
    tooLarge?: boolean;
    downloadUrl?: string | null;
}>();

const fullscreen = ref(false);

const LIB_SRC: Record<string, string> = {
    mermaid: '/plan-libs/mermaid.min.js',
    chart: '/plan-libs/chart.umd.min.js',
};
// Per-lib init that runs after the lib script loads (Chart.js needs none —
// author scripts call `new Chart(...)` directly against the global).
const LIB_INIT: Record<string, string> = {
    mermaid: 'mermaid.initialize({startOnLoad:true});',
    chart: '',
};

function appOrigin(): string {
    return typeof window !== 'undefined' ? window.location.origin : '';
}

const srcdoc = computed<string>(() => {
    if (props.format !== 'html' || !props.content || props.tooLarge) {
        return '';
    }

    const libs = (props.libs ?? []).filter((l) => l in LIB_SRC);
    const base = appOrigin();
    const libTags = libs
        .map((l) => `<script src="${base}${LIB_SRC[l]}"><\/script>`)
        .join('');
    const initJs = libs.map((l) => LIB_INIT[l]).filter(Boolean).join('');
    const initTag = initJs
        ? `<script>document.addEventListener('DOMContentLoaded',function(){${initJs}});<\/script>`
        : '';
    const head = libTags + initTag;

    const body = props.content;
    const isFullDoc = /^\s*<(!doctype|html)/i.test(body);

    if (isFullDoc) {
        if (/<\/head>/i.test(body)) {
            return body.replace(/<\/head>/i, `${head}</head>`);
        }

        return body.replace(/<body[^>]*>/i, (m) => `${m}${head}`);
    }

    return [
        '<!doctype html><html><head><meta charset="utf-8"><base target="_blank">',
        '<style>:root{color-scheme:light dark}html,body{margin:0;padding:14px 16px;font-family:ui-sans-serif,system-ui,sans-serif;font-size:13px;line-height:1.55}pre,code{font-family:ui-monospace,Menlo,monospace;font-size:12.5px}pre{padding:8px;border-radius:6px;background:rgba(127,127,127,.14);overflow-x:auto}table{border-collapse:collapse}th,td{border:1px solid rgba(127,127,127,.3);padding:4px 8px}img{max-width:100%;height:auto}a{color:#6366f1}</style>',
        head,
        '</head><body>',
        body,
        '</body></html>',
    ].join('');
});
</script>

<template>
    <MarkdownContent
        v-if="format === 'md' && content"
        :source="content"
        :interactive-tasks="false"
        class="px-3 py-2"
    />

    <div v-else-if="tooLarge" class="px-3 py-3 text-[12.5px] text-muted-foreground">
        Plan is too large to render inline —
        <a
            v-if="downloadUrl"
            :href="downloadUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="underline"
            >download it</a
        >.
    </div>

    <div v-else-if="format === 'html' && content">
        <div
            class="flex items-center justify-end gap-3 px-3 py-1 text-[11px] text-muted-foreground"
        >
            <button
                type="button"
                class="hover:text-foreground hover:underline"
                @click="fullscreen = true"
            >
                Fullscreen
            </button>
        </div>
        <iframe
            :srcdoc="srcdoc"
            sandbox="allow-scripts"
            title="Plan"
            class="block w-full border-0"
            style="height: 70vh; min-height: 360px"
        />

        <Teleport to="body">
            <div v-if="fullscreen" class="fixed inset-0 z-50 flex flex-col bg-background">
                <div
                    class="flex items-center justify-end gap-4 border-b border-border px-4 py-2 text-sm text-muted-foreground"
                >
                    <a
                        v-if="downloadUrl"
                        :href="downloadUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="hover:text-foreground hover:underline"
                        >Open raw</a
                    >
                    <button
                        type="button"
                        class="hover:text-foreground hover:underline"
                        @click="fullscreen = false"
                    >
                        Close ✕
                    </button>
                </div>
                <iframe
                    :srcdoc="srcdoc"
                    sandbox="allow-scripts"
                    title="Plan (fullscreen)"
                    class="block h-full w-full flex-1 border-0"
                />
            </div>
        </Teleport>
    </div>

    <div v-else class="px-3 py-3 text-[12.5px] text-muted-foreground">
        Plan content unavailable.
    </div>
</template>
