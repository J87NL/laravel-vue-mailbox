<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import Form from '@form-builder/components/Form.vue'
import type { FormSchema } from '@form-builder/types/form-builder'
import FolderList from '@mailbox/components/FolderList.vue'
import type { MailboxFolder, MailboxSummary } from '@mailbox/types'
import { ui } from '@mailbox/ui'

const props = defineProps<{
    backUrl: string
    folders: MailboxFolder[]
    form: FormSchema
    mailbox: MailboxSummary
    mode: 'new' | 'reply' | 'reply_all' | 'forward'
}>()

const titles: Record<typeof props.mode, string> = {
    forward: 'Doorsturen',
    new: 'Nieuw bericht',
    reply: 'Beantwoorden',
    reply_all: 'Allen beantwoorden',
}
</script>

<template>
    <Head :title="titles[mode]" />

    <div class="flex h-full flex-1 gap-4 p-4">
        <aside class="hidden w-60 shrink-0 md:block">
            <FolderList :folders="folders" />
        </aside>

        <main class="flex min-w-0 flex-1 flex-col gap-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">{{ titles[mode] }}</h1>
                    <p :class="ui.muted">Vanaf {{ mailbox.email }}</p>
                </div>
                <Link :href="backUrl" :class="ui.button">Annuleren</Link>
            </div>

            <div :class="ui.panel">
                <Form :schema="form" />
            </div>
        </main>
    </div>
</template>
