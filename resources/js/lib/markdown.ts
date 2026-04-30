import { marked } from 'marked';

marked.setOptions({
    gfm: true,
    breaks: true,
});

const RAW_HTML_RE = /<\/?(script|iframe|object|embed|style)[^>]*>/gi;
const ON_ATTR_RE = /\son\w+="[^"]*"/gi;

export function renderMarkdown(source: string | null | undefined): string {
    if (!source) return '';
    const html = marked.parse(source, { async: false }) as string;
    return html.replace(RAW_HTML_RE, '').replace(ON_ATTR_RE, '');
}
