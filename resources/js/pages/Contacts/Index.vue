<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import TableBuilder from '@table-builder/components/TableBuilder.vue'
import { useMailboxConfirm } from '@mailbox/confirm'
import type { TableData } from '@table-builder/types/table-builder'
import FolderList from '@mailbox/components/FolderList.vue'
import { useMailboxTranslations } from '@mailbox/translations'
import type { MailboxFolder, MailboxSummary } from '@mailbox/types'
import { ui } from '@mailbox/ui'

const confirm = useMailboxConfirm()

defineProps<{
    backUrl: string
    createUrl: string | null
    folders: MailboxFolder[]
    mailbox: MailboxSummary
    table: TableData
}>()

const { t } = useMailboxTranslations()
</script>

<template>
    <Head :title="`${t('contacts')} · ${mailbox.name}`" />

    <div class="flex h-full flex-1 gap-4 p-4">
        <aside class="hidden w-60 shrink-0 md:block">
            <FolderList :folders="folders" />
        </aside>

        <main class="flex min-w-0 flex-1 flex-col gap-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">{{ t('contacts') }}</h1>
                    <p :class="ui.muted">{{ mailbox.email }}</p>
                </div>
                <div :class="ui.toolbar">
                    <Link :href="backUrl" :class="ui.button">{{ t('back') }}</Link>
                    <Link v-if="createUrl" :href="createUrl" :class="ui.primaryButton">{{ t('new_contact') }}</Link>
                </div>
            </div>

            <div :class="ui.panel">
                <TableBuilder :table="table" :confirm="confirm" />
            </div>
        </main>
    </div>
</template>
