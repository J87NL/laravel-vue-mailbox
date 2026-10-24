<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import Form from '@form-builder/components/Form.vue'
import type { FormSchema } from '@form-builder/types/form-builder'
import TableBuilder from '@table-builder/components/TableBuilder.vue'
import { useMailboxConfirm } from '@mailbox/confirm'
import type { TableData } from '@table-builder/types/table-builder'
import FolderList from '@mailbox/components/FolderList.vue'
import { useMailboxTranslations } from '@mailbox/translations'
import type { MailboxFolder, MailboxSummary } from '@mailbox/types'
import { ui } from '@mailbox/ui'

const confirm = useMailboxConfirm()

const props = defineProps<{
    composeUrl: string | null
    contactsUrl: string
    folder: { id: number; label: string; name: string; role: string } | null
    folderForm: FormSchema | null
    folders: MailboxFolder[]
    mailbox: MailboxSummary
    mailboxes: MailboxSummary[]
    refreshUrl?: string | null
    settingsUrl: string | null
    syncing: string | null
    table: TableData | null
}>()

const { t } = useMailboxTranslations()

const refreshing = ref(false)

function refresh(): void {
    if (!props.refreshUrl || refreshing.value) {
        return
    }

    router.post(props.refreshUrl, {}, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            refreshing.value = true
        },
        onFinish: () => {
            refreshing.value = false
        },
    })
}

function switchMailbox(event: Event): void {
    const id = Number((event.target as HTMLSelectElement).value)
    const target = props.mailboxes.find((item) => item.id === id)

    if (target?.url) {
        router.visit(target.url)
    }
}
</script>

<template>
    <Head :title="folder ? `${folder.label} · ${mailbox.name}` : mailbox.name" />

    <div class="flex h-full flex-1 gap-4 p-4">
        <aside class="hidden w-60 shrink-0 flex-col gap-4 md:flex">
            <Link v-if="composeUrl" :href="composeUrl" :class="[ui.primaryButton, 'w-full']">{{ t('compose') }}</Link>

            <FolderList :folders="folders" :active-id="folder?.id" />

            <Link :href="contactsUrl" class="rounded-md px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800">
                {{ t('contacts') }}
            </Link>

            <details v-if="folderForm" class="text-sm">
                <summary class="cursor-pointer text-gray-500 dark:text-gray-400">{{ t('add_folder') }}</summary>
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
                    <select
                        v-if="mailboxes.length > 1"
                        :value="mailbox.id"
                        :class="ui.select"
                        :aria-label="t('switch_mailbox')"
                        @change="switchMailbox"
                    >
                        <option v-for="item in mailboxes" :key="item.id" :value="item.id">
                            {{ item.unseen ? `${item.name} (${item.unseen})` : item.name }}
                        </option>
                    </select>
                    <button v-if="refreshUrl" type="button" :class="ui.button" :disabled="refreshing" @click="refresh">
                        {{ refreshing ? t('refreshing') : t('refresh') }}
                    </button>
                    <Link :href="contactsUrl" :class="[ui.button, 'md:hidden']">{{ t('contacts') }}</Link>
                    <Link v-if="settingsUrl" :href="settingsUrl" :class="ui.button">{{ t('settings') }}</Link>
                    <Link v-if="composeUrl" :href="composeUrl" :class="[ui.primaryButton, 'md:hidden']">{{ t('new') }}</Link>
                </div>
            </div>

            <div v-if="syncing" :class="[ui.panel, 'text-center']">
                <p :class="ui.muted">{{ syncing }}</p>
            </div>

            <div v-else-if="table" :class="ui.panel">
                <TableBuilder :table="table" :only="['table', 'folders']" :confirm="confirm" />
            </div>
        </main>
    </div>
</template>
