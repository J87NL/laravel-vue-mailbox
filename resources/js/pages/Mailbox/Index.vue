<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import Form from '@form-builder/components/Form.vue'
import type { FormSchema } from '@form-builder/types/form-builder'
import TableBuilder from '@table-builder/components/TableBuilder.vue'
import type { TableData } from '@table-builder/types/table-builder'
import FolderList from '@mailbox/components/FolderList.vue'
import type { MailboxFolder, MailboxSummary } from '@mailbox/types'
import { ui } from '@mailbox/ui'

defineProps<{
    composeUrl: string | null
    folder: { id: number; label: string; name: string; role: string } | null
    folderForm: FormSchema | null
    folders: MailboxFolder[]
    mailbox: MailboxSummary
    mailboxes: MailboxSummary[]
    settingsUrl: string | null
    syncing: string | null
    table: TableData | null
}>()
</script>

<template>
    <Head :title="folder ? `${folder.label} · ${mailbox.name}` : mailbox.name" />

    <div class="flex h-full flex-1 gap-4 p-4">
        <aside class="hidden w-60 shrink-0 flex-col gap-4 md:flex">
            <Link v-if="composeUrl" :href="composeUrl" :class="[ui.primaryButton, 'w-full']">Nieuw bericht</Link>

            <div v-if="mailboxes.length > 1" class="flex flex-col gap-1 text-sm">
                <Link
                    v-for="item in mailboxes"
                    :key="item.id"
                    :href="item.url!"
                    class="truncate rounded-md px-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-800"
                    :class="{ 'font-semibold': item.id === mailbox.id }"
                >
                    {{ item.name }}
                </Link>
            </div>

            <FolderList :folders="folders" :active-id="folder?.id" />

            <details v-if="folderForm" class="text-sm">
                <summary class="cursor-pointer text-gray-500 dark:text-gray-400">Map toevoegen</summary>
                <div class="mt-2">
                    <Form :schema="folderForm" />
                </div>
            </details>
        </aside>

        <main class="flex min-w-0 flex-1 flex-col gap-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">{{ folder?.label ?? mailbox.name }}</h1>
                    <p :class="ui.muted">{{ mailbox.email }}</p>
                </div>
                <div :class="ui.toolbar">
                    <Link v-if="settingsUrl" :href="settingsUrl" :class="ui.button">Instellingen</Link>
                    <Link v-if="composeUrl" :href="composeUrl" :class="[ui.primaryButton, 'md:hidden']">Nieuw</Link>
                </div>
            </div>

            <div v-if="syncing" :class="[ui.panel, 'text-center']">
                <p :class="ui.muted">{{ syncing }}</p>
            </div>

            <div v-else-if="table" :class="ui.panel">
                <TableBuilder :table="table" :row-class="(row) => (row.is_seen ? '' : 'font-semibold')" />
            </div>
        </main>
    </div>
</template>
