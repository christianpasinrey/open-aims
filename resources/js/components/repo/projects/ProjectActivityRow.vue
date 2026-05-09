<script setup lang="ts">
import { computed } from 'vue';
import Avatar from '@/components/repo/Avatar.vue';

type Actor = { id: number; name: string; email: string };
type Activity = {
    id: number;
    kind: string;
    payload: Record<string, unknown> | null;
    occurred_at: string | null;
    actor: Actor | null;
};

const props = defineProps<{ activity: Activity }>();

function relativeTime(iso: string | null): string {
    if (!iso) {
        return '';
    }
    const d = new Date(iso).getTime();
    const diff = Math.max(0, Date.now() - d);
    const m = Math.floor(diff / 60000);
    if (m < 1) return 'just now';
    if (m < 60) return `${m}m ago`;
    const h = Math.floor(m / 60);
    if (h < 24) return `${h}h ago`;
    const days = Math.floor(h / 24);
    if (days < 30) return `${days}d ago`;
    const months = Math.floor(days / 30);
    if (months < 12) return `${months}mo ago`;
    return `${Math.floor(months / 12)}y ago`;
}

function payloadString(key: string): string | undefined {
    const v = props.activity.payload?.[key];
    return typeof v === 'string' ? v : undefined;
}

const actorName = computed(() => props.activity.actor?.name ?? 'Someone');

const STATE_LABELS: Record<string, string> = {
    backlog: 'Backlog',
    planned: 'Planned',
    started: 'In progress',
    paused: 'Paused',
    completed: 'Completed',
    canceled: 'Canceled',
};
function stateLabel(key: string | undefined): string {
    if (!key) return '—';
    return STATE_LABELS[key] ?? key;
}
</script>

<template>
    <li
        class="flex items-start gap-2 py-1.5 text-[12.5px] text-muted-foreground"
    >
        <Avatar
            v-if="activity.actor"
            :name="activity.actor.name"
            :email="activity.actor.email"
            :size="16"
            class="mt-0.5"
        />
        <span
            v-else
            class="mt-1 size-1.5 rounded-full bg-muted"
            aria-hidden="true"
        ></span>

        <div class="min-w-0 flex-1 leading-relaxed">
            <span class="text-foreground">{{ actorName }}</span>

            <template v-if="activity.kind === 'created'">
                <span> created the project</span>
            </template>

            <template v-else-if="activity.kind === 'name_changed'">
                <span> renamed the project to </span>
                <span class="text-foreground">{{
                    payloadString('to') ?? '—'
                }}</span>
            </template>

            <template v-else-if="activity.kind === 'description_changed'">
                <span> updated the description</span>
            </template>

            <template v-else-if="activity.kind === 'state_changed'">
                <span> changed status from </span>
                <span class="text-foreground">{{
                    stateLabel(payloadString('from'))
                }}</span>
                <span> to </span>
                <span class="text-foreground">{{
                    stateLabel(payloadString('to'))
                }}</span>
            </template>

            <template v-else-if="activity.kind === 'priority_changed'">
                <span>
                    set priority to
                    <span class="text-foreground">{{
                        payloadString('to_label') ?? '—'
                    }}</span>
                </span>
            </template>

            <template v-else-if="activity.kind === 'lead_set'">
                <span>
                    set lead to
                    <span class="text-foreground">{{
                        payloadString('user_name') ?? 'someone'
                    }}</span>
                </span>
            </template>
            <template v-else-if="activity.kind === 'lead_unset'">
                <span> removed the project lead</span>
            </template>

            <template v-else-if="activity.kind === 'start_date_changed'">
                <span v-if="payloadString('to')">
                    set start date to
                    <span class="text-foreground">{{
                        payloadString('to')
                    }}</span>
                </span>
                <span v-else> cleared the start date</span>
            </template>
            <template v-else-if="activity.kind === 'target_date_changed'">
                <span v-if="payloadString('to')">
                    set target date to
                    <span class="text-foreground">{{
                        payloadString('to')
                    }}</span>
                </span>
                <span v-else> cleared the target date</span>
            </template>

            <template v-else-if="activity.kind === 'milestone_added'">
                <span> added milestone </span>
                <span class="text-foreground">{{
                    payloadString('milestone_name') ?? '—'
                }}</span>
            </template>

            <template v-else-if="activity.kind === 'member_added'">
                <span> added </span>
                <span class="text-foreground">{{
                    payloadString('user_name') ?? 'someone'
                }}</span>
                <span> as a member</span>
            </template>
            <template v-else-if="activity.kind === 'member_removed'">
                <span> removed </span>
                <span class="text-foreground">{{
                    payloadString('user_name') ?? 'someone'
                }}</span>
            </template>

            <template
                v-else-if="
                    activity.kind === 'label_added' ||
                    activity.kind === 'label_removed'
                "
            >
                <span>{{
                    activity.kind === 'label_added'
                        ? ' added label '
                        : ' removed label '
                }}</span>
                <span
                    class="inline-flex items-center gap-1 rounded-full border border-border bg-card px-1.5 py-px text-[11px] text-foreground"
                >
                    <span
                        class="size-1.5 rounded-full"
                        :style="{
                            backgroundColor:
                                payloadString('label_color') ?? '#94a3b8',
                        }"
                    ></span>
                    <span>{{ payloadString('label_name') ?? '—' }}</span>
                </span>
            </template>

            <template v-else-if="activity.kind === 'resource_added'">
                <span> added resource </span>
                <span class="text-foreground">{{
                    payloadString('resource_name') ?? '—'
                }}</span>
            </template>
            <template v-else-if="activity.kind === 'resource_removed'">
                <span> removed resource </span>
                <span class="text-foreground">{{
                    payloadString('resource_name') ?? '—'
                }}</span>
            </template>

            <template v-else-if="activity.kind === 'trashed'">
                <span> moved the project to Trash</span>
            </template>
            <template v-else-if="activity.kind === 'restored'">
                <span> restored the project from Trash</span>
            </template>

            <template v-else-if="activity.kind === 'branch_linked'">
                <span> linked branch </span>
                <span class="font-mono text-foreground">{{
                    payloadString('branch_name') ?? '—'
                }}</span>
            </template>
            <template v-else-if="activity.kind === 'branch_merged'">
                <span> merged </span>
                <span class="font-mono text-foreground">{{
                    payloadString('branch_name') ?? '—'
                }}</span>
                <span v-if="payloadString('base_branch')"> into </span>
                <span
                    v-if="payloadString('base_branch')"
                    class="font-mono text-foreground"
                    >{{ payloadString('base_branch') }}</span
                >
            </template>

            <template v-else>
                <span> {{ activity.kind.replace(/_/g, ' ') }}</span>
            </template>

            <span class="ml-1 text-muted-foreground/80">·</span>
            <span class="ml-1 text-muted-foreground/80">{{
                relativeTime(activity.occurred_at)
            }}</span>
        </div>
    </li>
</template>
