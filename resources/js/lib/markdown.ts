import { marked } from 'marked';

marked.setOptions({
    gfm: true,
    breaks: true,
});

const RAW_HTML_RE = /<\/?(script|iframe|object|embed|style)[^>]*>/gi;
const ON_ATTR_RE = /\son\w+="[^"]*"/gi;
const TABLE_RE = /<table\b[\s\S]*?<\/table>/gi;

export function renderMarkdown(source: string | null | undefined): string {
    if (!source) {
        return '';
    }

    const html = marked.parse(source, { async: false }) as string;
    const sanitized = html.replace(RAW_HTML_RE, '').replace(ON_ATTR_RE, '');

    // Wrap tables in horizontal-scroll containers so wide tables don't push
    // the right rail off-screen on narrow viewports.
    return sanitized.replace(
        TABLE_RE,
        (match) => `<div class="overflow-x-auto">${match}</div>`,
    );
}
