<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Avatar from '@/components/repo/Avatar.vue';
import IssueRefHoverCard from '@/components/repo/IssueRefHoverCard.vue';
import StatusIcon from '@/components/repo/StatusIcon.vue';

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
function payloadNumber(key: string): number | undefined {
    const v = props.activity.payload?.[key];
    return typeof v === 'number' ? v : undefined;
}
function payloadObject(key: string): Record<string, unknown> | undefined {
    const v = props.activity.payload?.[key];
    return v && typeof v === 'object' && !Array.isArray(v)
        ? (v as Record<string, unknown>)
        : undefined;
}

const actorName = computed(() => props.activity.actor?.name ?? 'Someone');
const fromState = computed(() => payloadObject('from'));
const toState = computed(() => payloadObject('to'));
const targetIdentifier = computed(() => payloadString('target_identifier'));
const targetTitle = computed(() => payloadString('target_title'));
const relationType = computed(() => payloadString('relation_type'));
const labelName = computed(() => payloadString('label_name'));
const labelColor = computed(() => payloadString('label_color'));
const projectName = computed(() => payloadString('project_name'));
const userName = computed(() => payloadString('user_name'));
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
                <span> created the issue</span>
            </template>

            <template v-else-if="activity.kind === 'status_changed'">
                <span> changed status from </span>
                <span class="inline-flex items-center gap-1">
                    <StatusIcon
                        v-if="fromState"
                        :type="(fromState.type as string) ?? 'unstarted'"
                    />
                    <span class="text-foreground">{{
                        fromState?.name ?? '—'
                    }}</span>
                </span>
                <span> to </span>
                <span class="inline-flex items-center gap-1">
                    <StatusIcon
                        v-if="toState"
                        :type="(toState.type as string) ?? 'unstarted'"
                    />
                    <span class="text-foreground">{{
                        toState?.name ?? '—'
                    }}</span>
                </span>
            </template>

            <template v-else-if="activity.kind === 'priority_changed'">
                <span>
                    set priority to
                    <span class="text-foreground">{{
                        payloadString('to_label') ?? '—'
                    }}</span></span
                >
            </template>

            <template v-else-if="activity.kind === 'assigned'">
                <span>
                    assigned to
                    <span class="text-foreground">{{
                        userName ?? '—'
                    }}</span></span
                >
            </template>
            <template v-else-if="activity.kind === 'unassigned'">
                <span> unassigned the issue</span>
            </template>

            <template v-else-if="activity.kind === 'project_set'">
                <span> added the issue to project </span>
                <Link
                    v-if="payloadString('project_slug')"
                    :href="`/projects/${payloadString('project_slug')}`"
                    class="text-foreground hover:underline"
                    >{{ projectName ?? '—' }}</Link
                >
                <span v-else class="text-foreground">{{
                    projectName ?? '—'
                }}</span>
            </template>
            <template v-else-if="activity.kind === 'project_unset'">
                <span> removed the issue from its project</span>
            </template>

            <template v-else-if="activity.kind === 'cycle_set'">
                <span>
                    moved the issue to
                    <span class="text-foreground">{{
                        payloadString('cycle_name') ?? 'a cycle'
                    }}</span></span
                >
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
                        :style="{ backgroundColor: labelColor ?? '#94a3b8' }"
                    ></span>
                    <span>{{ labelName ?? '—' }}</span>
                </span>
            </template>

            <template v-else-if="activity.kind === 'relation_added'">
                <span> added related issue </span>
                <IssueRefHoverCard
                    v-if="targetIdentifier"
                    :identifier="targetIdentifier"
                >
                    {{ targetIdentifier }}
                </IssueRefHoverCard>
                <span v-if="relationType === 'blocks'" class="text-foreground">
                    as blocking</span
                >
                <span v-if="targetTitle" class="text-muted-foreground">
                    — {{ targetTitle }}</span
                >
            </template>
            <template v-else-if="activity.kind === 'relation_removed'">
                <span> removed relation to </span>
                <IssueRefHoverCard
                    v-if="targetIdentifier"
                    :identifier="targetIdentifier"
                />
            </template>

            <template v-else-if="activity.kind === 'mentioned'">
                <span> mentioned </span>
                <span class="text-foreground"
                    >@{{
                        (
                            activity.payload?.mentioned_user_names as
                                | string[]
                                | undefined
                        )?.[0] ?? 'someone'
                    }}</span
                >
            </template>

            <template v-else-if="activity.kind === 'title_changed'">
                <span> renamed the issue to </span>
                <span class="text-foreground">{{
                    payloadString('to') ?? '—'
                }}</span>
            </template>

            <template v-else-if="activity.kind === 'description_changed'">
                <span> updated the description</span>
            </template>

            <template v-else-if="activity.kind === 'cycle_unset'">
                <span> removed the issue from its cycle</span>
            </template>

            <template v-else-if="activity.kind === 'due_date_changed'">
                <span v-if="payloadString('to')">
                    set the due date to
                    <span class="text-foreground">{{
                        payloadString('to')
                    }}</span>
                </span>
                <span v-else> cleared the due date</span>
            </template>

            <template v-else-if="activity.kind === 'estimate_changed'">
                <span
                    v-if="
                        payloadNumber('to') !== undefined &&
                        payloadNumber('to') !== 0
                    "
                >
                    set the estimate to
                    <span class="text-foreground">{{
                        payloadNumber('to')
                    }}</span>
                </span>
                <span v-else> cleared the estimate</span>
            </template>

            <template v-else-if="activity.kind === 'archived'">
                <span> archived the issue</span>
            </template>
            <template v-else-if="activity.kind === 'unarchived'">
                <span> unarchived the issue</span>
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
