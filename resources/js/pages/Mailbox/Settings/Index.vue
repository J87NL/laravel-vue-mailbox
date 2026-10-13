<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import TableBuilder from '@table-builder/components/TableBuilder.vue'
import type { TableData } from '@table-builder/types/table-builder'
import { ui } from '@mailbox/ui'

defineProps<{
    createUrl: string | null
    table: TableData
}>()
</script>

<template>
    <Head title="Mailboxen" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Mailboxen</h1>
            <Link v-if="createUrl" :href="createUrl" :class="ui.primaryButton">Nieuwe mailbox</Link>
        </div>

        <div :class="ui.panel">
            <TableBuilder :table="table" />
        </div>
    </div>
</template>
