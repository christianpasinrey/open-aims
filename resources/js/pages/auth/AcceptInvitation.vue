<script setup lang="ts">
defineProps<{
    valid: boolean
    token?: string
    email?: string
    workspaceName?: string
    accountExists?: boolean
}>()
</script>

<template>
    <div>
        <template v-if="valid">
            <p>Accept invitation to {{ workspaceName }}</p>
        </template>
        <template v-else>
            <p>This invitation is invalid or has expired.</p>
        </template>
    </div>
</template>
