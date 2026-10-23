<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ui } from '@mailbox/ui'

defineProps<{
    hint: string
    title: string
}>()
</script>

<template>
    <Head :title="title" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div :class="[ui.panel, 'flex flex-col items-center gap-2 py-12 text-center']">
            <h1 class="text-lg font-semibold">{{ title }}</h1>
            <p :class="ui.muted">{{ hint }}</p>
        </div>
    </div>
</template>
