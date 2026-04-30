<script setup lang="ts">
import type { HoverCardRootEmits, HoverCardRootProps } from 'reka-ui';
import { HoverCardRoot, useForwardPropsEmits } from 'reka-ui';

const props = withDefaults(defineProps<HoverCardRootProps>(), {
    openDelay: 250,
    closeDelay: 120,
});
const emits = defineEmits<HoverCardRootEmits>();

const forwarded = useForwardPropsEmits(props, emits);
</script>

<template>
    <HoverCardRoot v-bind="forwarded">
        <slot />
    </HoverCardRoot>
</template>
