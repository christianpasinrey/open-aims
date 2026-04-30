import { marked } from 'marked';

marked.setOptions({
    gfm: true,
    breaks: true,
});

const RAW_HTML_RE = /<\/?(script|iframe|object|embed|style)[^>]*>/gi;
const ON_ATTR_RE = /\son\w+="[^"]*"/gi;
const TABLE_RE = /<table\b[\s\S]*?<\/table>/gi;

const ISSUE_REF_RE = /\b([A-Z][A-Z0-9]*-\d+)\b/g;
const TAG_SPLIT_RE = /(<[^>]+>)/;
const CODE_OPEN_RE = /^<(code|pre)\b/i;
const CODE_CLOSE_RE = /^<\/(code|pre)>/i;

/**
 * Wrap LAM-XXX style identifiers in inline placeholders that <MarkdownContent>
 * later substitutes with hover-card-aware components. Skips `<code>` / `<pre>`
 * regions so identifiers inside code blocks render as plain text.
 */
function wrapIssueRefs(html: string): string {
    const parts = html.split(TAG_SPLIT_RE);
    let inCode = 0;
    let i = 0;

    return parts
        .map((part) => {
            const isTag = i++ % 2 === 1;
            if (isTag) {
                if (CODE_OPEN_RE.test(part)) {
                    inCode++;
                }
                if (CODE_CLOSE_RE.test(part)) {
                    inCode = Math.max(0, inCode - 1);
                }
                return part;
            }
            if (inCode > 0) {
                return part;
            }
            return part.replace(ISSUE_REF_RE, '<<ISSUE-REF:$1>>');
        })
        .join('');
}

/**
 * repo stores inline issue references in descriptions as
 *   <issue id="UUID">LAM-128</issue>
 * Strip the wrapper before markdown parsing so the inner identifier is left
 * as a plain LAM-XXX token that wrapIssueRefs() then tags as a hover-card ref.
 */
const REPO_ISSUE_TAG_RE =
    /<issue\b[^>]*>([A-Z][A-Z0-9]*-\d+)<\/issue>/g;

export function renderMarkdown(source: string | null | undefined): string {
    if (!source) {
        return '';
    }

    const preprocessed = source.replace(REPO_ISSUE_TAG_RE, '$1');
    const html = marked.parse(preprocessed, { async: false }) as string;
    const sanitized = html.replace(RAW_HTML_RE, '').replace(ON_ATTR_RE, '');

    const withTables = sanitized.replace(
        TABLE_RE,
        (match) => `<div class="overflow-x-auto">${match}</div>`,
    );

    // marked emits task-list checkboxes as `<input type="checkbox" disabled>`.
    // Strip the disabled attribute so MarkdownContent can wire click handlers.
    const enabledTasks = withTables.replace(
        /<input([^>]*?)\s+disabled(\s|=|>)/gi,
        '<input$1$2',
    );

    return wrapIssueRefs(enabledTasks);
}
