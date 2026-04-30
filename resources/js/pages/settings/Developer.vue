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

const claudeConfig = `{
  "mcpServers": {
    "aims": {
      "type": "http",
      "url": "${props.mcp.endpoint}",
      "auth": {
        "type": "oauth",
        "authorize_url": "${props.mcp.oauth_authorize}",
        "token_url": "${props.mcp.oauth_token}",
        "scopes": ["mcp"]
      }
    }
  }
}`;
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

        <!-- Setup steps -->
        <section class="space-y-3">
            <h3 class="text-[13px] font-medium text-foreground">Setup</h3>
            <ol class="space-y-3 text-[13px] text-muted-foreground">
                <li class="flex gap-3">
                    <span
                        class="flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-foreground"
                        >1</span
                    >
                    <span>
                        Open Claude Desktop → Settings → Connectors → "Add
                        custom connector".
                    </span>
                </li>
                <li class="flex gap-3">
                    <span
                        class="flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-foreground"
                        >2</span
                    >
                    <span>
                        Paste the connector URL above. Claude will discover
                        the OAuth endpoints automatically.
                    </span>
                </li>
                <li class="flex gap-3">
                    <span
                        class="flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-foreground"
                        >3</span
                    >
                    <span>
                        Approve the authorisation prompt — you'll be sent
                        back here, signed into your aims workspace,
                        and the connector goes green.
                    </span>
                </li>
                <li class="flex gap-3">
                    <span
                        class="flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-foreground"
                        >4</span
                    >
                    <span>
                        Claude can now read and operate aims. Try:
                        <em>"List LAM issues in In Review"</em> or
                        <em>"Set LAM-275 to In Progress"</em>.
                    </span>
                </li>
            </ol>
        </section>

        <!-- Manual config snippet (for Claude Code, claude.ai, etc.) -->
        <section class="space-y-2">
            <h3 class="text-[13px] font-medium text-foreground">
                Manual config (Claude Code / advanced)
            </h3>
            <p class="text-[12.5px] text-muted-foreground">
                For Claude Code's <code class="rounded bg-muted px-1 py-0.5 font-mono text-[11.5px]">.mcp.json</code>
                or claude.ai's MCP block.
            </p>
            <div class="relative">
                <pre
                    class="overflow-x-auto rounded-md border border-border bg-card px-3 py-2.5 font-mono text-[12px] leading-snug text-foreground"
                >{{ claudeConfig }}</pre>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="absolute top-1.5 right-1.5 h-7 gap-1 text-[12px]"
                    @click="copy(claudeConfig, 'Config')"
                >
                    <Copy class="size-3.5" />
                    Copy
                </Button>
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
