<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Terminal, Copy, ExternalLink, AlertTriangle, Trash2, Layers } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Profile settings', href: '/settings/profile' },
            { title: 'Developer', href: '/settings/developer' },
        ],
    },
});

type ConnectedClient = {
    client_id: string;
    name: string;
    kind: string;
    redirect_uri: string | null;
    token_count: number;
    scopes: string[];
    last_authorised_at: string | null;
};

const props = defineProps<{
    mcp: {
        endpoint: string;
        oauth_authorize: string;
        oauth_token: string;
        clients: ConnectedClient[];
        connected: boolean;
        status: 'not_connected' | 'connected';
    };
}>();

function fmtDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function revokeClient(clientId: string, kind: string) {
    if (!confirm(`Revoke every active token for ${kind}? This signs Claude out of aims from that client.`)) {
        return;
    }
    router.delete(`/settings/developer/clients/${encodeURIComponent(clientId)}`, {
        preserveScroll: true,
    });
}

function keepLatestForClient(clientId: string) {
    router.post(
        `/settings/developer/clients/${encodeURIComponent(clientId)}/keep-latest`,
        {},
        { preserveScroll: true },
    );
}

function dedupeAll() {
    if (!confirm('Keep only the latest token per client and revoke the rest?')) return;
    router.post('/settings/developer/clients/dedupe', {}, { preserveScroll: true });
}

const copied = ref<string | null>(null);
function copy(value: string, label: string) {
    if (typeof navigator === 'undefined' || !navigator.clipboard) {
        toast.error('Clipboard not available');
        return;
    }
    navigator.clipboard
        .writeText(value)
        .then(() => {
            copied.value = label;
            toast.success(`${label} copied`);
            setTimeout(() => (copied.value = null), 1500);
        })
        .catch(() => toast.error('Could not copy'));
}

const claudeCodeConfig = `{
  "mcpServers": {
    "aims": {
      "type": "http",
      "url": "${props.mcp.endpoint}"
    }
  }
}`;

const claudeCodeCli = `claude mcp add --transport http aims ${props.mcp.endpoint}`;
</script>

<template>
    <Head title="Developer · Settings" />

    <h1 class="sr-only">Developer settings</h1>

    <div class="flex flex-col space-y-8">
        <Heading
            variant="small"
            title="Connect Claude (MCP)"
            description="Hook Claude Desktop and Claude Code into your aims workspace via Model Context Protocol. The agent can list issues, create projects, transition cycles — everything you can do in the UI."
        />

        <!-- Status banner -->
        <div
            v-if="mcp.status !== 'connected'"
            class="flex items-start gap-3 rounded-md border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-[13px]"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0 text-amber-500" />
            <div class="space-y-1">
                <div class="font-medium text-foreground">
                    Not connected
                </div>
                <p class="text-muted-foreground">
                    No active MCP token for your account. Follow the setup
                    below — once you authorise from Claude Desktop or Claude
                    Code this banner turns green.
                </p>
            </div>
        </div>
        <div
            v-else
            class="space-y-3 rounded-md border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-[13px]"
        >
            <div class="flex items-start gap-3">
                <Terminal class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                <div class="space-y-1">
                    <div class="font-medium text-foreground">
                        Connected · {{ mcp.clients.length }}
                        device{{ mcp.clients.length === 1 ? '' : 's' }}
                    </div>
                    <p class="text-muted-foreground">
                        Claude is authorised to operate this workspace from
                        the device(s) below.
                    </p>
                </div>
            </div>
        </div>

        <!-- Connected devices table -->
        <section v-if="mcp.clients.length" class="space-y-3">
            <div class="flex items-center justify-between">
                <h3 class="text-[13px] font-medium text-foreground">
                    Connected devices
                </h3>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-8 gap-1 text-[12px]"
                    @click="dedupeAll"
                >
                    <Layers class="size-3.5" />
                    Keep latest per device
                </Button>
            </div>
            <ul class="divide-y divide-border rounded-md border border-border">
                <li
                    v-for="c in mcp.clients"
                    :key="c.client_id"
                    class="flex items-center gap-3 px-3 py-2.5 text-[12.5px]"
                >
                    <span class="size-2 shrink-0 rounded-full bg-emerald-500"></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 text-foreground">
                            <span class="font-medium">{{ c.kind }}</span>
                            <span
                                v-if="c.platform"
                                class="rounded-full border border-border bg-muted px-2 py-0.5 text-[10.5px] uppercase tracking-wide text-muted-foreground"
                            >
                                {{ c.platform }}<span v-if="c.browser && c.browser !== 'Other'"> · {{ c.browser }}</span>
                            </span>
                            <span
                                v-if="c.token_count > 1"
                                class="rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[10.5px] uppercase tracking-wide text-amber-500"
                            >
                                {{ c.token_count }} tokens
                            </span>
                        </div>
                        <div class="mt-0.5 text-[11.5px] text-muted-foreground">
                            <span v-if="c.ip" class="font-mono">{{ c.ip }}</span>
                            <span v-if="c.ip"> · </span>
                            <span>last auth {{ fmtDate(c.last_authorised_at) }}</span>
                        </div>
                    </div>
                    <Button
                        v-if="c.token_count > 1"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-7 text-[12px] text-muted-foreground hover:text-foreground"
                        @click="keepLatestForClient(c.client_id)"
                    >
                        Dedupe
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-7 gap-1 text-[12px] text-destructive hover:bg-destructive/10 hover:text-destructive"
                        @click="revokeClient(c.client_id, c.kind)"
                    >
                        <Trash2 class="size-3.5" />
                        Revoke
                    </Button>
                </li>
            </ul>
        </section>

        <!-- Connector URL -->
        <section class="space-y-2">
            <h3 class="text-[13px] font-medium text-foreground">Connector URL</h3>
            <p class="text-[12.5px] text-muted-foreground">
                Paste this into Claude Desktop's "Add custom connector" /
                "Add MCP server" dialog. OAuth handles the rest.
            </p>
            <div
                class="flex items-center gap-2 rounded-md border border-border bg-card px-3 py-2 font-mono text-[12.5px]"
            >
                <code class="flex-1 break-all text-foreground">{{ mcp.endpoint }}</code>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-7 gap-1 text-[12px]"
                    @click="copy(mcp.endpoint, 'URL')"
                >
                    <Copy class="size-3.5" />
                    Copy
                </Button>
            </div>
        </section>

        <!-- Claude Desktop -->
        <section class="space-y-3 rounded-md border border-border bg-card p-5">
            <div class="flex items-center justify-between">
                <h3 class="text-[14px] font-semibold text-foreground">
                    Claude Desktop
                </h3>
                <span
                    class="rounded-full border border-border bg-muted px-2 py-0.5 text-[10.5px] font-medium uppercase tracking-wide text-muted-foreground"
                >Recommended</span>
            </div>
            <p class="text-[12.5px] text-muted-foreground">
                One-click OAuth from Claude's UI. No JSON to edit.
            </p>
            <ol class="space-y-2 text-[13px] text-muted-foreground">
                <li class="flex gap-3">
                    <span
                        class="flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-foreground"
                        >1</span
                    >
                    <span>
                        Settings → Connectors → <strong class="text-foreground">Add custom connector</strong>.
                    </span>
                </li>
                <li class="flex gap-3">
                    <span
                        class="flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-foreground"
                        >2</span
                    >
                    <span>
                        Paste the connector URL from above. Claude discovers
                        the OAuth endpoints automatically.
                    </span>
                </li>
                <li class="flex gap-3">
                    <span
                        class="flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-foreground"
                        >3</span
                    >
                    <span>
                        Approve in the consent screen → connector turns green.
                        Try: <em>"List LAM issues in In Review"</em> or
                        <em>"Set LAM-275 to In Progress"</em>.
                    </span>
                </li>
            </ol>
        </section>

        <!-- Claude Code -->
        <section class="space-y-3 rounded-md border border-border bg-card p-5">
            <h3 class="text-[14px] font-semibold text-foreground">
                Claude Code (CLI / IDE)
            </h3>
            <p class="text-[12.5px] text-muted-foreground">
                Two ways. Pick one.
            </p>

            <div class="space-y-2">
                <div class="flex items-center gap-2 text-[12.5px] text-foreground">
                    <span
                        class="flex size-5 items-center justify-center rounded-full bg-brand text-[11px] font-semibold text-brand-foreground"
                        >A</span
                    >
                    <span>One-line CLI command (uses Claude Code's built-in MCP store)</span>
                </div>
                <div class="relative">
                    <pre
                        class="overflow-x-auto rounded-md border border-border bg-muted/30 px-3 py-2.5 font-mono text-[12px] leading-snug text-foreground"
                    >{{ claudeCodeCli }}</pre>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="absolute top-1.5 right-1.5 h-7 gap-1 text-[12px]"
                        @click="copy(claudeCodeCli, 'Command')"
                    >
                        <Copy class="size-3.5" />
                        Copy
                    </Button>
                </div>
                <p class="text-[12px] text-muted-foreground">
                    Run it once. The next session opens a browser for OAuth
                    consent and you're done — Claude Code can call
                    <code class="rounded bg-muted px-1 py-0.5 font-mono text-[11.5px]">aims.issues.list</code>
                    and friends from any prompt.
                </p>
            </div>

            <div class="space-y-2 pt-1">
                <div class="flex items-center gap-2 text-[12.5px] text-foreground">
                    <span
                        class="flex size-5 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-foreground"
                        >B</span
                    >
                    <span>Or commit to repo via <code class="rounded bg-muted px-1 py-0.5 font-mono text-[11.5px]">.mcp.json</code></span>
                </div>
                <p class="text-[12px] text-muted-foreground">
                    Drop this at the project root. Anyone who clones the repo
                    and runs Claude Code will be prompted to authorise.
                </p>
                <div class="relative">
                    <pre
                        class="overflow-x-auto rounded-md border border-border bg-muted/30 px-3 py-2.5 font-mono text-[12px] leading-snug text-foreground"
                    >{{ claudeCodeConfig }}</pre>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="absolute top-1.5 right-1.5 h-7 gap-1 text-[12px]"
                        @click="copy(claudeCodeConfig, 'Config')"
                    >
                        <Copy class="size-3.5" />
                        Copy
                    </Button>
                </div>
            </div>
        </section>

        <!-- Tools preview -->
        <section class="space-y-2">
            <h3 class="text-[13px] font-medium text-foreground">
                Available tools
            </h3>
            <p class="text-[12.5px] text-muted-foreground">
                Anything you can click in the UI, Claude can drive via the
                MCP. Scoped to your active workspace.
            </p>
            <ul
                class="grid grid-cols-1 gap-1 text-[12.5px] text-muted-foreground sm:grid-cols-2"
            >
                <li>· workspace.current</li>
                <li>· workspace.search</li>
                <li>· issues.list / get / create / update</li>
                <li>· issues.transition / archive / delete / comment</li>
                <li>· projects.list / get / create / update</li>
                <li>· projects.add_milestone</li>
                <li>· cycles.list / get / create</li>
                <li>· initiatives.list / create</li>
                <li>· views.list</li>
                <li>· inbox.list</li>
            </ul>
        </section>

        <!-- Tokens (placeholder) -->
        <section v-if="mcp.tokens.length" class="space-y-2">
            <h3 class="text-[13px] font-medium text-foreground">
                Personal access tokens
            </h3>
            <ul class="divide-y divide-border rounded-md border border-border">
                <li
                    v-for="t in mcp.tokens"
                    :key="t.id"
                    class="flex items-center justify-between gap-3 px-3 py-2 text-[12.5px]"
                >
                    <div>
                        <div class="font-medium text-foreground">{{ t.name }}</div>
                        <div class="text-muted-foreground">
                            Last used {{ t.last_used_at ?? 'never' }}
                        </div>
                    </div>
                    <span
                        class="rounded-full border border-border bg-card px-2 py-0.5 text-[11px] text-muted-foreground"
                        >{{ t.scopes.join(', ') || 'mcp' }}</span
                    >
                </li>
            </ul>
        </section>

        <p class="text-[11.5px] text-muted-foreground">
            <a
                href="https://modelcontextprotocol.io"
                target="_blank"
                rel="noreferrer"
                class="inline-flex items-center gap-1 hover:text-foreground"
            >
                Learn about MCP
                <ExternalLink class="size-3" />
            </a>
        </p>
    </div>
</template>
