<script setup lang="ts">
import type { HoverCardTriggerProps } from 'reka-ui';
import { HoverCardTrigger, useForwardProps } from 'reka-ui';

const props = defineProps<HoverCardTriggerProps>();
const forwarded = useForwardProps(props);
</script>

<template>
    <HoverCardTrigger v-bind="forwarded">
        <slot />
    </HoverCardTrigger>
</template>
