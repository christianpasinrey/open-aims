<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Plus } from 'lucide-vue-next';
import ProjectChip from '@/components/repo/ProjectChip.vue';
import ProjectIcon from '@/components/repo/ProjectIcon.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type Project = {
    id: number;
    name: string;
    slug?: string | null;
    color?: string | null;
    icon?: string | null;
};

const props = defineProps<{
    identifier: string;
    projects: Project[];
    current: Project | null;
}>();

function pick(id: number | null): void {
    if ((props.current?.id ?? null) === id) {
        return;
    }

    router.patch(
        `/issues/${props.identifier}`,
        { project_id: id },
        { preserveScroll: true },
    );
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="flex w-full items-center gap-2 rounded text-left text-[13px]"
                aria-label="Project"
            >
                <ProjectChip
                    v-if="current"
                    :name="current.name"
                    :color="current.color"
                    :icon="current.icon"
                    :slug="current.slug"
                />
                <span
                    v-else
                    class="inline-flex items-center gap-1.5 rounded-md border border-dashed border-border px-2 py-1 text-[12px] text-muted-foreground transition-colors hover:border-foreground hover:text-foreground"
                >
                    <Plus class="size-3" />
                    <span>Add to project</span>
                </span>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="start"
            class="max-h-72 w-60 overflow-y-auto"
        >
            <DropdownMenuItem @select="pick(null)">
                <span
                    class="size-3.5 rounded-full border border-dashed border-border"
                ></span>
                <span class="flex-1">No project</span>
                <Check v-if="!current" class="size-3.5 text-foreground" />
            </DropdownMenuItem>
            <DropdownMenuSeparator v-if="projects.length" />
            <DropdownMenuItem
                v-for="p in projects"
                :key="p.id"
                @select="pick(p.id)"
            >
                <ProjectIcon :icon="p.icon" :color="p.color" :size="14" />
                <span class="flex-1 truncate">{{ p.name }}</span>
                <Check
                    v-if="current?.id === p.id"
                    class="size-3.5 text-foreground"
                />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
