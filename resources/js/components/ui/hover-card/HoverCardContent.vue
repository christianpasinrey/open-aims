<script setup lang="ts">
import type { HoverCardContentProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { reactiveOmit } from '@vueuse/core';
import { HoverCardContent, HoverCardPortal, useForwardProps } from 'reka-ui';
import { cn } from '@/lib/utils';

defineOptions({
    inheritAttrs: false,
});

const props = withDefaults(
    defineProps<HoverCardContentProps & { class?: HTMLAttributes['class'] }>(),
    {
        sideOffset: 6,
        align: 'start',
        collisionPadding: 12,
    },
);

const delegatedProps = reactiveOmit(props, 'class');
const forwarded = useForwardProps(delegatedProps);
</script>

<template>
    <HoverCardPortal>
        <HoverCardContent
            data-slot="hover-card-content"
            v-bind="{ ...$attrs, ...forwarded }"
            :class="
                cn(
                    'bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-1 data-[side=left]:slide-in-from-right-1 data-[side=right]:slide-in-from-left-1 data-[side=top]:slide-in-from-bottom-1 z-50 w-72 origin-(--reka-hover-card-content-transform-origin) rounded-md border border-border/60 p-3 text-[13px] shadow-md outline-none',
                    props.class,
                )
            "
        >
            <slot />
        </HoverCardContent>
    </HoverCardPortal>
</template>
