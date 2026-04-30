<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Terminal, Copy, ExternalLink, AlertTriangle } from 'lucide-vue-next';
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

type Token = {
    id: number;
    name: string;
    last_used_at: string | null;
    scopes: string[];
};

const props = defineProps<{
    mcp: {
        endpoint: string;
        oauth_authorize: string;
        oauth_token: string;
        tokens: Token[];
        connected: boolean;
        status: 'not_configured' | 'configured' | 'connected';
    };
}>();

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
                    Not connected yet
                </div>
                <p class="text-muted-foreground">
                    The MCP server endpoint and OAuth scaffolding land in
                    the next deploy. The connector URL below is final — you
                    can already register it in Claude and the OAuth handshake
                    will activate once the server boots.
                </p>
            </div>
        </div>
        <div
            v-else
            class="flex items-start gap-3 rounded-md border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-[13px]"
        >
            <Terminal class="mt-0.5 size-4 shrink-0 text-emerald-500" />
            <div class="space-y-1">
                <div class="font-medium text-foreground">Connected</div>
                <p class="text-muted-foreground">
                    Claude is authorised to operate this workspace.
                </p>
            </div>
        </div>

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
