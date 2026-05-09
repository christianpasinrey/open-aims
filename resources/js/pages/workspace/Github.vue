<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import {
    ExternalLink,
    GitMerge,
    GitPullRequest,
    Github,
    Plug,
    TriangleAlert,
} from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';

type Installation = {
    id: number;
    installation_id: string;
    account_login: string;
    account_type: string;
    repository_selection: string;
    suspended_at: string | null;
    created_at: string | null;
};

type TeamRow = {
    id: number;
    key: string;
    name: string;
    color: string | null;
    repo_full_name: string | null;
};

type RecentPullRequest = {
    id: number;
    number: number;
    title: string;
    state: 'open' | 'closed' | 'merged' | string;
    url: string;
    branch_name: string;
    author_login: string | null;
    opened_at: string | null;
    issue: { identifier: string; title: string } | null;
};

type BranchRow = {
    id: number;
    name: string;
    head_sha: string | null;
    repo_full_name: string | null;
    last_pusher_login: string | null;
    last_pushed_at: string | null;
    html_url: string | null;
};

type PullRow = {
    id: number;
    number: number;
    title: string;
    state: 'open' | 'closed' | 'merged' | string;
    merged: boolean;
    draft: boolean;
    head_branch_name: string | null;
    base_ref: string | null;
    html_url: string | null;
    author_login: string | null;
    opened_at: string | null;
    closed_at: string | null;
    merged_at: string | null;
    repo_full_name: string | null;
};

type WebhookEventRow = {
    id: number;
    event_type: string;
    action: string | null;
    repository_full_name: string | null;
    sender_login: string | null;
    signature_ok: boolean;
    processed_at: string | null;
    processing_error: string | null;
    received_at: string | null;
};

defineProps<{
    configured: boolean;
    installUrl: string;
    appName: string;
    installations: Installation[];
    teams: TeamRow[];
    recentPullRequests: RecentPullRequest[];
    branches: BranchRow[];
    pulls: PullRow[];
    events: WebhookEventRow[];
}>();

const sidebarNavItems = [
    { title: 'General', href: '/workspace/settings' },
    { title: 'Members', href: '/workspace/members' },
    { title: 'GitHub', href: '/workspace/github' },
];

const page = usePage();
const flashStatus = computed<string | null>(() => {
    const url = new URL(window.location.href);
    return url.searchParams.get('status');
});
const linkedCount = computed<number | null>(() => {
    const url = new URL(window.location.href);
    const v = url.searchParams.get('linked');
    return v === null ? null : Number(v);
});
const branchesCount = computed<number | null>(() => {
    const url = new URL(window.location.href);
    const v = url.searchParams.get('branches');
    return v === null ? null : Number(v);
});
const pullsCount = computed<number | null>(() => {
    const url = new URL(window.location.href);
    const v = url.searchParams.get('pulls');
    return v === null ? null : Number(v);
});
const attachedCount = computed<number | null>(() => {
    const url = new URL(window.location.href);
    const v = url.searchParams.get('attached');
    return v === null ? null : Number(v);
});
const errorMessage = computed<string | null>(() => {
    const errors = (page.props.errors as Record<string, string>) ?? {};
    return errors.github_app ?? null;
});

function pillClass(state: string): string {
    switch (state) {
        case 'merged':
            return 'bg-purple-500/15 text-purple-400 ring-1 ring-purple-500/30';
        case 'closed':
            return 'bg-rose-500/15 text-rose-400 ring-1 ring-rose-500/30';
        default:
            return 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30';
    }
}
function pillLabel(state: string): string {
    if (state === 'merged') {
        return 'Merged';
    }
    if (state === 'closed') {
        return 'Closed';
    }
    return 'Open';
}

function fmtDate(iso: string | null): string {
    if (!iso) {
        return '—';
    }
    return new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function relativeTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }
    const then = new Date(iso).getTime();
    if (Number.isNaN(then)) {
        return '—';
    }
    const diff = Date.now() - then;
    if (diff < 0) {
        return 'just now';
    }
    const sec = Math.floor(diff / 1000);
    if (sec < 60) {
        return sec <= 5 ? 'just now' : `${sec}s ago`;
    }
    const min = Math.floor(sec / 60);
    if (min < 60) {
        return `${min}m ago`;
    }
    const hr = Math.floor(min / 60);
    if (hr < 24) {
        return `${hr}h ago`;
    }
    const day = Math.floor(hr / 24);
    if (day < 30) {
        return `${day}d ago`;
    }
    const mo = Math.floor(day / 30);
    if (mo < 12) {
        return `${mo}mo ago`;
    }
    const yr = Math.floor(day / 365);
    return `${yr}y ago`;
}
</script>

<template>
    <Head title="GitHub integration · Workspace" />

    <div class="px-4 py-6">
        <Heading
            title="Workspace settings"
            description="Manage your workspace and how it appears to teammates."
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav
                    class="flex flex-col space-y-1"
                    aria-label="Workspace settings"
                >
                    <Link
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-md px-3 py-2 text-[13px] font-medium transition-colors"
                        :class="
                            item.href === '/workspace/github'
                                ? 'bg-muted text-foreground'
                                : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'
                        "
                    >
                        {{ item.title }}
                    </Link>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-3xl">
                <div class="flex flex-col space-y-8">
                    <Heading
                        variant="small"
                        title="GitHub integration"
                        description="Connect a GitHub App so pull requests in your organization auto-link to issues by branch name."
                    />

                    <p
                        v-if="errorMessage"
                        class="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-[12.5px] text-destructive"
                    >
                        {{ errorMessage }}
                    </p>
                    <p
                        v-if="flashStatus === 'installed'"
                        class="rounded-md border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-[12.5px] text-emerald-400"
                    >
                        GitHub App installed.
                    </p>
                    <p
                        v-else-if="flashStatus === 'reconciled'"
                        class="rounded-md border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-[12.5px] text-emerald-400"
                    >
                        Reconcile complete.
                        <span v-if="attachedCount !== null"
                            >{{ attachedCount }} installation(s) attached to
                            this workspace.</span
                        >
                    </p>
                    <p
                        v-else-if="flashStatus === 'synced'"
                        class="rounded-md border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-[12.5px] text-emerald-400"
                    >
                        <span
                            v-if="
                                branchesCount !== null && pullsCount !== null
                            "
                        >
                            Fetch complete. {{ branchesCount }} branches,
                            {{ pullsCount }} pull requests,
                            {{ linkedCount ?? 0 }} linked to issues.
                        </span>
                        <span v-else>
                            Sync complete.
                            <span v-if="linkedCount !== null"
                                >{{ linkedCount }} pull request(s) linked or
                                updated.</span
                            >
                        </span>
                    </p>

                    <!-- Installation -->
                    <section class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h2
                                class="text-[13px] font-semibold text-foreground"
                            >
                                Installation
                            </h2>
                            <span
                                v-if="!configured"
                                class="inline-flex items-center gap-1 rounded bg-amber-500/15 px-2 py-0.5 text-[10px] font-medium tracking-wide text-amber-400 uppercase ring-1 ring-amber-500/30"
                            >
                                <TriangleAlert class="size-3" />
                                App not configured
                            </span>
                        </div>

                        <div
                            v-if="!configured"
                            class="rounded-md border border-dashed border-border bg-muted/30 px-4 py-3 text-[12.5px] text-muted-foreground"
                        >
                            <p>
                                Set
                                <code class="font-mono">GITHUB_APP_ID</code>,
                                <code class="font-mono">GITHUB_APP_NAME</code>
                                and
                                <code class="font-mono"
                                    >GITHUB_APP_WEBHOOK_SECRET</code
                                >, plus the App's private key one of two
                                ways:
                            </p>
                            <ul class="ml-4 mt-1.5 list-disc space-y-0.5">
                                <li>
                                    inline as
                                    <code class="font-mono"
                                        >GITHUB_APP_PRIVATE_KEY</code
                                    >
                                    (the full PEM, newlines preserved or
                                    escaped as
                                    <code class="font-mono">\n</code>); or
                                </li>
                                <li>
                                    on disk via
                                    <code class="font-mono"
                                        >GITHUB_APP_PRIVATE_KEY_PATH</code
                                    >
                                    (defaults to
                                    <code class="font-mono"
                                        >storage/keys/github-app.pem</code
                                    >).
                                </li>
                            </ul>
                        </div>

                        <div
                            v-else-if="installations.length === 0"
                            class="flex items-center justify-between rounded-md border border-border bg-card px-4 py-3"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex size-9 items-center justify-center rounded-md bg-muted text-foreground"
                                >
                                    <Github class="size-5" />
                                </span>
                                <div>
                                    <div
                                        class="text-[13px] font-medium text-foreground"
                                    >
                                        No installations yet
                                    </div>
                                    <div
                                        class="text-[12px] text-muted-foreground"
                                    >
                                        Install the GitHub App on your
                                        organization, or click "Already
                                        installed?" to adopt an existing
                                        installation.
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Form
                                    action="/gh/reconcile"
                                    method="post"
                                    v-slot="{ processing }"
                                >
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        size="sm"
                                        :disabled="processing"
                                    >
                                        {{
                                            processing
                                                ? 'Looking up…'
                                                : 'Already installed?'
                                        }}
                                    </Button>
                                </Form>
                                <a
                                    :href="installUrl"
                                    class="inline-flex h-8 items-center rounded-md bg-foreground px-3 text-[12px] font-medium text-background transition-opacity hover:opacity-90"
                                >
                                    Install on GitHub
                                </a>
                            </div>
                        </div>

                        <div
                            v-else
                            v-for="install in installations"
                            :key="install.id"
                            class="flex items-center justify-between rounded-md border border-border bg-card px-4 py-3"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex size-9 items-center justify-center rounded-md bg-muted text-foreground"
                                >
                                    <Github class="size-5" />
                                </span>
                                <div>
                                    <div
                                        class="flex items-center gap-2 text-[13px] font-medium text-foreground"
                                    >
                                        <span class="font-mono">{{
                                            install.account_login
                                        }}</span>
                                        <span
                                            class="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                                            >{{ install.account_type }}</span
                                        >
                                        <span
                                            v-if="install.suspended_at"
                                            class="rounded bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-medium tracking-wide text-amber-400 uppercase ring-1 ring-amber-500/30"
                                            >Suspended</span
                                        >
                                    </div>
                                    <div
                                        class="text-[12px] text-muted-foreground"
                                    >
                                        Repository selection:
                                        <span class="font-mono">{{
                                            install.repository_selection
                                        }}</span>
                                        · Installed
                                        {{ fmtDate(install.created_at) }}
                                    </div>
                                </div>
                            </div>
                            <Form
                                action="/gh/sync"
                                method="post"
                                v-slot="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    :disabled="processing"
                                >
                                    <Plug class="mr-1.5 size-3.5" />
                                    {{
                                        processing
                                            ? 'Fetching…'
                                            : 'Fetch from GitHub'
                                    }}
                                </Button>
                            </Form>
                        </div>
                    </section>

                    <!-- Repository mapping -->
                    <section class="space-y-3">
                        <h2 class="text-[13px] font-semibold text-foreground">
                            Repository mapping
                        </h2>
                        <div
                            class="overflow-hidden rounded-md border border-border bg-card"
                        >
                            <table class="w-full text-[13px]">
                                <thead>
                                    <tr
                                        class="border-b border-border bg-muted/30"
                                    >
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Team
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Repository
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        ></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="team in teams"
                                        :key="team.id"
                                        class="border-b border-border last:border-b-0"
                                    >
                                        <td class="h-9 px-3 py-1.5">
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    class="flex size-5 items-center justify-center rounded-md text-[10px] font-semibold text-white"
                                                    :style="{
                                                        backgroundColor:
                                                            team.color ||
                                                            '#6366f1',
                                                    }"
                                                >
                                                    {{ team.key.charAt(0) }}
                                                </span>
                                                <span
                                                    class="font-mono text-[12px]"
                                                    >{{ team.key }}</span
                                                >
                                                <span
                                                    class="text-muted-foreground"
                                                    >{{ team.name }}</span
                                                >
                                            </div>
                                        </td>
                                        <td class="h-9 px-3 py-1.5">
                                            <span
                                                v-if="team.repo_full_name"
                                                class="font-mono text-[12px] text-foreground"
                                                >{{ team.repo_full_name }}</span
                                            >
                                            <span
                                                v-else
                                                class="text-[12px] text-muted-foreground"
                                                >—</span
                                            >
                                        </td>
                                        <td class="h-9 px-3 py-1.5 text-right">
                                            <Link
                                                :href="`/teams/${team.key}/settings`"
                                                class="text-[11.5px] text-muted-foreground hover:text-foreground"
                                            >
                                                Edit
                                            </Link>
                                        </td>
                                    </tr>
                                    <tr v-if="teams.length === 0">
                                        <td
                                            colspan="3"
                                            class="px-3 py-3 text-center text-[12px] text-muted-foreground"
                                        >
                                            No teams in this workspace.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-[11.5px] text-muted-foreground">
                            Edit each team's repository in its
                            <span class="font-mono">Team settings</span> page.
                            Teams without a repository fall back to the
                            <code class="font-mono"
                                >GITHUB_APP_REPO_&lt;KEY&gt;</code
                            >
                            env var if present.
                        </p>
                    </section>

                    <!-- Branches -->
                    <section class="space-y-3">
                        <h2 class="text-[13px] font-semibold text-foreground">
                            Branches
                        </h2>
                        <div
                            v-if="branches.length === 0"
                            class="rounded-md border border-dashed border-border bg-muted/30 px-3 py-3 text-[12px] text-muted-foreground"
                        >
                            No branches yet — push to a repo this app can see
                            and they'll show up here.
                        </div>
                        <div
                            v-else
                            class="overflow-hidden rounded-md border border-border bg-card"
                        >
                            <table class="w-full text-[13px]">
                                <thead>
                                    <tr
                                        class="border-b border-border bg-muted/30"
                                    >
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Branch
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Repository
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            HEAD
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Pusher
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Pushed
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="branch in branches"
                                        :key="branch.id"
                                        class="border-b border-border last:border-b-0"
                                    >
                                        <td
                                            class="h-9 max-w-[260px] px-3 py-1.5"
                                        >
                                            <a
                                                v-if="branch.html_url"
                                                :href="branch.html_url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="inline-flex items-center gap-1.5 truncate font-mono text-[11.5px] text-foreground hover:underline"
                                            >
                                                <span class="truncate">{{
                                                    branch.name
                                                }}</span>
                                                <ExternalLink
                                                    class="size-3 shrink-0 text-muted-foreground"
                                                />
                                            </a>
                                            <span
                                                v-else
                                                class="font-mono text-[11.5px] text-foreground"
                                                >{{ branch.name }}</span
                                            >
                                        </td>
                                        <td
                                            class="h-9 max-w-[220px] truncate px-3 py-1.5"
                                        >
                                            <span
                                                v-if="branch.repo_full_name"
                                                class="truncate font-mono text-[11.5px] text-muted-foreground"
                                                >{{ branch.repo_full_name }}</span
                                            >
                                            <span
                                                v-else
                                                class="text-[11.5px] text-muted-foreground"
                                                >—</span
                                            >
                                        </td>
                                        <td class="h-9 px-3 py-1.5">
                                            <span
                                                v-if="branch.head_sha"
                                                class="font-mono text-[11px] text-muted-foreground"
                                                >{{ branch.head_sha }}</span
                                            >
                                            <span
                                                v-else
                                                class="text-[11px] text-muted-foreground"
                                                >—</span
                                            >
                                        </td>
                                        <td
                                            class="h-9 px-3 py-1.5 text-[12px] text-muted-foreground"
                                        >
                                            {{
                                                branch.last_pusher_login || '—'
                                            }}
                                        </td>
                                        <td
                                            class="h-9 px-3 py-1.5 text-[12px] text-muted-foreground"
                                        >
                                            {{
                                                relativeTime(
                                                    branch.last_pushed_at,
                                                )
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Pull requests -->
                    <section class="space-y-3">
                        <h2 class="text-[13px] font-semibold text-foreground">
                            Pull requests
                        </h2>
                        <div
                            v-if="pulls.length === 0"
                            class="rounded-md border border-dashed border-border bg-muted/30 px-3 py-3 text-[12px] text-muted-foreground"
                        >
                            No pull requests yet — open a PR on a repo this app
                            can see and they'll show up here.
                        </div>
                        <div
                            v-else
                            class="overflow-hidden rounded-md border border-border bg-card"
                        >
                            <table class="w-full text-[13px]">
                                <thead>
                                    <tr
                                        class="border-b border-border bg-muted/30"
                                    >
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            PR
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            State
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Branch
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Repository
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Author
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Opened
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="pr in pulls"
                                        :key="pr.id"
                                        class="border-b border-border last:border-b-0"
                                    >
                                        <td
                                            class="h-9 max-w-[280px] px-3 py-1.5"
                                        >
                                            <a
                                                v-if="pr.html_url"
                                                :href="pr.html_url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="flex items-center gap-2 truncate text-foreground hover:underline"
                                            >
                                                <component
                                                    :is="
                                                        pr.merged
                                                            ? GitMerge
                                                            : GitPullRequest
                                                    "
                                                    class="size-3.5 shrink-0 text-muted-foreground"
                                                />
                                                <span
                                                    class="font-mono text-[11px] text-muted-foreground"
                                                    >#{{ pr.number }}</span
                                                >
                                                <span class="truncate">{{
                                                    pr.title
                                                }}</span>
                                                <ExternalLink
                                                    class="size-3 shrink-0 text-muted-foreground"
                                                />
                                            </a>
                                            <span
                                                v-else
                                                class="flex items-center gap-2 truncate"
                                            >
                                                <span
                                                    class="font-mono text-[11px] text-muted-foreground"
                                                    >#{{ pr.number }}</span
                                                >
                                                <span class="truncate">{{
                                                    pr.title
                                                }}</span>
                                            </span>
                                        </td>
                                        <td class="h-9 px-3 py-1.5">
                                            <span
                                                v-if="pr.draft"
                                                class="inline-flex rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium tracking-wide text-muted-foreground uppercase ring-1 ring-border"
                                            >
                                                Draft
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-medium tracking-wide uppercase"
                                                :class="
                                                    pillClass(
                                                        pr.merged
                                                            ? 'merged'
                                                            : pr.state,
                                                    )
                                                "
                                            >
                                                {{
                                                    pillLabel(
                                                        pr.merged
                                                            ? 'merged'
                                                            : pr.state,
                                                    )
                                                }}
                                            </span>
                                        </td>
                                        <td
                                            class="h-9 max-w-[220px] truncate px-3 py-1.5"
                                        >
                                            <span
                                                class="truncate font-mono text-[11px] text-muted-foreground"
                                            >
                                                {{
                                                    pr.head_branch_name || '—'
                                                }}
                                                <span
                                                    v-if="pr.base_ref"
                                                    class="text-muted-foreground/70"
                                                >
                                                    →
                                                    {{ pr.base_ref }}
                                                </span>
                                            </span>
                                        </td>
                                        <td
                                            class="h-9 max-w-[200px] truncate px-3 py-1.5"
                                        >
                                            <span
                                                v-if="pr.repo_full_name"
                                                class="truncate font-mono text-[11px] text-muted-foreground"
                                                >{{ pr.repo_full_name }}</span
                                            >
                                            <span
                                                v-else
                                                class="text-[11px] text-muted-foreground"
                                                >—</span
                                            >
                                        </td>
                                        <td
                                            class="h-9 px-3 py-1.5 text-[12px] text-muted-foreground"
                                        >
                                            {{ pr.author_login || '—' }}
                                        </td>
                                        <td
                                            class="h-9 px-3 py-1.5 text-muted-foreground"
                                        >
                                            {{ fmtDate(pr.opened_at) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Recent webhook activity -->
                    <section class="space-y-3">
                        <h2 class="text-[13px] font-semibold text-foreground">
                            Recent webhook activity
                        </h2>
                        <div
                            v-if="events.length === 0"
                            class="rounded-md border border-dashed border-border bg-muted/30 px-3 py-3 text-[12px] text-muted-foreground"
                        >
                            No webhooks received yet.
                        </div>
                        <div
                            v-else
                            class="overflow-hidden rounded-md border border-border bg-card"
                        >
                            <table class="w-full text-[13px]">
                                <thead>
                                    <tr
                                        class="border-b border-border bg-muted/30"
                                    >
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Event
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Repository
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Sender
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Signature
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Status
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Received
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="evt in events"
                                        :key="evt.id"
                                        class="border-b border-border last:border-b-0"
                                    >
                                        <td class="h-9 px-3 py-1.5">
                                            <span
                                                class="font-mono text-[11.5px] text-foreground"
                                                >{{ evt.event_type }}</span
                                            >
                                            <span
                                                v-if="evt.action"
                                                class="font-mono text-[11px] text-muted-foreground"
                                            >
                                                ·
                                                {{ evt.action }}
                                            </span>
                                        </td>
                                        <td
                                            class="h-9 max-w-[220px] truncate px-3 py-1.5"
                                        >
                                            <span
                                                v-if="evt.repository_full_name"
                                                class="truncate font-mono text-[11px] text-muted-foreground"
                                                >{{
                                                    evt.repository_full_name
                                                }}</span
                                            >
                                            <span
                                                v-else
                                                class="text-[11px] text-muted-foreground"
                                                >—</span
                                            >
                                        </td>
                                        <td
                                            class="h-9 px-3 py-1.5 text-[12px] text-muted-foreground"
                                        >
                                            {{ evt.sender_login || '—' }}
                                        </td>
                                        <td class="h-9 px-3 py-1.5">
                                            <span
                                                v-if="evt.signature_ok"
                                                class="font-mono text-[12px] text-emerald-400"
                                                aria-label="Signature OK"
                                                >✓</span
                                            >
                                            <span
                                                v-else
                                                class="font-mono text-[12px] text-rose-400"
                                                aria-label="Signature failed"
                                                >✗</span
                                            >
                                        </td>
                                        <td class="h-9 px-3 py-1.5">
                                            <div class="flex flex-col leading-tight">
                                                <span
                                                    class="text-[11.5px]"
                                                    :class="
                                                        evt.processed_at
                                                            ? 'text-emerald-400'
                                                            : 'text-amber-400'
                                                    "
                                                >
                                                    {{
                                                        evt.processed_at
                                                            ? 'Processed'
                                                            : 'Pending'
                                                    }}
                                                </span>
                                                <span
                                                    v-if="
                                                        evt.processing_error
                                                    "
                                                    class="truncate text-[10.5px] text-muted-foreground"
                                                    :title="
                                                        evt.processing_error
                                                    "
                                                >
                                                    {{
                                                        evt.processing_error.slice(
                                                            0,
                                                            60,
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </td>
                                        <td
                                            class="h-9 px-3 py-1.5 text-[12px] text-muted-foreground"
                                        >
                                            {{
                                                relativeTime(evt.received_at)
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Recent PR activity -->
                    <section class="space-y-3">
                        <h2 class="text-[13px] font-semibold text-foreground">
                            Recent pull request activity
                        </h2>
                        <div
                            v-if="recentPullRequests.length === 0"
                            class="rounded-md border border-dashed border-border bg-muted/30 px-3 py-3 text-[12px] text-muted-foreground"
                        >
                            No pull requests linked yet.
                        </div>
                        <div
                            v-else
                            class="overflow-hidden rounded-md border border-border bg-card"
                        >
                            <table class="w-full text-[13px]">
                                <thead>
                                    <tr
                                        class="border-b border-border bg-muted/30"
                                    >
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            PR
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            State
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Branch
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Issue
                                        </th>
                                        <th
                                            class="px-3 py-2 text-left text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Opened
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="pr in recentPullRequests"
                                        :key="pr.id"
                                        class="border-b border-border last:border-b-0"
                                    >
                                        <td
                                            class="h-9 max-w-[280px] px-3 py-1.5"
                                        >
                                            <a
                                                :href="pr.url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="flex items-center gap-2 truncate text-foreground hover:underline"
                                            >
                                                <component
                                                    :is="
                                                        pr.state === 'merged'
                                                            ? GitMerge
                                                            : GitPullRequest
                                                    "
                                                    class="size-3.5 shrink-0 text-muted-foreground"
                                                />
                                                <span
                                                    class="font-mono text-[11px] text-muted-foreground"
                                                    >#{{ pr.number }}</span
                                                >
                                                <span class="truncate">{{
                                                    pr.title
                                                }}</span>
                                                <ExternalLink
                                                    class="size-3 shrink-0 text-muted-foreground"
                                                />
                                            </a>
                                        </td>
                                        <td class="h-9 px-3 py-1.5">
                                            <span
                                                class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-medium tracking-wide uppercase"
                                                :class="pillClass(pr.state)"
                                            >
                                                {{ pillLabel(pr.state) }}
                                            </span>
                                        </td>
                                        <td
                                            class="h-9 max-w-[200px] truncate px-3 py-1.5"
                                        >
                                            <span
                                                class="truncate font-mono text-[11px] text-muted-foreground"
                                                >{{ pr.branch_name }}</span
                                            >
                                        </td>
                                        <td class="h-9 px-3 py-1.5">
                                            <a
                                                v-if="pr.issue"
                                                :href="`/issues/${pr.issue.identifier}`"
                                                class="font-mono text-[11px] text-muted-foreground hover:text-foreground"
                                                >{{ pr.issue.identifier }}</a
                                            >
                                            <span
                                                v-else
                                                class="text-[11px] text-muted-foreground"
                                                >—</span
                                            >
                                        </td>
                                        <td
                                            class="h-9 px-3 py-1.5 text-muted-foreground"
                                        >
                                            {{ fmtDate(pr.opened_at) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>
