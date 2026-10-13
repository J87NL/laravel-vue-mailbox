<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import Form from '@form-builder/components/Form.vue'
import type { FormSchema } from '@form-builder/types/form-builder'
import FolderList from '@mailbox/components/FolderList.vue'
import { useMailboxTranslations } from '@mailbox/translations'
import type { MailboxFolder, MailboxSummary } from '@mailbox/types'
import { ui } from '@mailbox/ui'

const props = defineProps<{
    backUrl: string
    folders: MailboxFolder[]
    form: FormSchema
    mailbox: MailboxSummary
    mode: 'new' | 'reply' | 'reply_all' | 'forward'
}>()

const { t } = useMailboxTranslations()

const title = computed(() => t(props.mode === 'new' ? 'compose' : props.mode))
</script>

<template>
    <Head :title="title" />

    <div class="flex h-full flex-1 gap-4 p-4">
        <aside class="hidden w-60 shrink-0 md:block">
            <FolderList :folders="folders" />
        </aside>

        <main class="flex min-w-0 flex-1 flex-col gap-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">{{ title }}</h1>
                    <p :class="ui.muted">{{ t('sent_from', { email: mailbox.email }) }}</p>
                </div>
                <Link :href="backUrl" :class="ui.button">{{ t('cancel') }}</Link>
            </div>

            <div :class="ui.panel">
                <Form :schema="form" />
            </div>
        </main>
    </div>
</template>
