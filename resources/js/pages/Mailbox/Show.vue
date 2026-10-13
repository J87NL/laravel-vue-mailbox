<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import FolderList from '@mailbox/components/FolderList.vue'
import MessageFrame from '@mailbox/components/MessageFrame.vue'
import type { AuditEntry, MailAddress, MailboxAttachment, MailboxFolder, MailboxSummary } from '@mailbox/types'
import { useMailboxTranslations } from '@mailbox/translations'
import { ui } from '@mailbox/ui'

const props = defineProps<{
    actionsUrl: string | null
    attachments: MailboxAttachment[]
    backUrl: string
    composeUrl: string | null
    contactUrl: string | null
    folders: MailboxFolder[]
    hasRemoteImages: boolean
    history: AuditEntry[]
    html: string
    imageOrigin: string
    remoteImages: boolean
    mailbox: MailboxSummary
    message: {
        id: number
        subject: string | null
        from_email: string | null
        from_name: string | null
        to: MailAddress[] | null
        cc: MailAddress[] | null
        sent_at: string | null
        is_flagged: boolean
        is_answered: boolean
    }
}>()

const { t } = useMailboxTranslations()

const moveTarget = ref<number | ''>('')

function act(action: string, extra: Record<string, unknown> = {}): void {
    if (!props.actionsUrl) {
        return
    }

    if (action === 'delete' && !confirm(t('message_delete_confirm'))) {
        return
    }

    router.post(props.actionsUrl, { action, ids: [props.message.id], ...extra }, { preserveScroll: true })
}

function move(): void {
    if (moveTarget.value === '') {
        return
    }

    act('move', { folder_id: moveTarget.value })
}

function addContact(): void {
    if (props.contactUrl) {
        router.post(props.contactUrl, {}, { preserveScroll: true })
    }
}

function replyUrl(mode: string): string {
    return `${props.composeUrl}?reply=${props.message.id}&mode=${mode}`
}

function addresses(list: MailAddress[] | null): string {
    return (list ?? []).map((address) => address.name ? `${address.name} <${address.email}>` : address.email).join(', ')
}
</script>

<template>
    <Head :title="message.subject ?? t('no_subject')" />

    <div class="flex h-full flex-1 gap-4 p-4">
        <aside class="hidden w-60 shrink-0 md:block">
            <FolderList :folders="folders" />
        </aside>

        <main class="flex min-w-0 flex-1 flex-col gap-4">
            <div :class="ui.toolbar">
                <Link :href="backUrl" :class="ui.button">{{ t('back') }}</Link>
                <template v-if="composeUrl">
                    <Link :href="replyUrl('reply')" :class="ui.primaryButton">{{ t('reply') }}</Link>
                    <Link :href="replyUrl('reply_all')" :class="ui.button">{{ t('reply_all') }}</Link>
                    <Link :href="replyUrl('forward')" :class="ui.button">{{ t('forward') }}</Link>
                </template>
                <template v-if="actionsUrl">
                    <button type="button" :class="ui.button" @click="act('archive')">{{ t('archive') }}</button>
                    <button type="button" :class="ui.dangerButton" @click="act('delete')">{{ t('delete') }}</button>
                    <button type="button" :class="ui.button" @click="act('unread')">{{ t('mark_unread') }}</button>
                    <button type="button" :class="ui.button" @click="act(message.is_flagged ? 'unflag' : 'flag')">
                        {{ message.is_flagged ? t('unflag') : t('flag') }}
                    </button>
                    <select v-model="moveTarget" :class="ui.select" @change="move">
                        <option value="">{{ t('move_placeholder') }}</option>
                        <option v-for="folder in folders" :key="folder.id" :value="folder.id">{{ "\u00a0".repeat(folder.depth * 2) }}{{ folder.label }}</option>
                    </select>
                </template>
                <button v-if="contactUrl" type="button" :class="ui.button" @click="addContact">{{ t('add_contact') }}</button>
            </div>

            <article :class="ui.panel">
                <header class="mb-4 border-b border-gray-200 pb-3 dark:border-gray-700">
                    <h1 class="text-xl font-semibold">{{ message.subject ?? t('no_subject') }}</h1>
                    <dl class="mt-2 grid grid-cols-[4rem_1fr] gap-x-2 text-sm text-gray-600 dark:text-gray-300">
                        <dt>{{ t('from') }}</dt>
                        <dd>{{ message.from_name ? `${message.from_name} <${message.from_email}>` : message.from_email }}</dd>
                        <dt>{{ t('to') }}</dt>
                        <dd>{{ addresses(message.to) }}</dd>
                        <template v-if="message.cc?.length">
                            <dt>{{ t('cc') }}</dt>
                            <dd>{{ addresses(message.cc) }}</dd>
                        </template>
                        <dt>{{ t('date') }}</dt>
                        <dd>{{ message.sent_at ? new Date(message.sent_at).toLocaleString() : '' }}</dd>
                    </dl>
                </header>

                <p v-if="hasRemoteImages" class="mb-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-100">
                    {{ t('remote_images_blocked') }}
                    <Link href="?images=1" class="underline" preserve-scroll>{{ t('load_images') }}</Link>
                </p>

                <MessageFrame :html="html" :image-origin="imageOrigin" :remote-images="remoteImages" />

                <ul v-if="attachments.length" class="mt-4 flex flex-wrap gap-2">
                    <li v-for="attachment in attachments" :key="attachment.url">
                        <a :href="attachment.url" :class="ui.button">{{ attachment.filename ?? t('attachment') }}</a>
                    </li>
                </ul>
            </article>

            <section v-if="history.length" :class="ui.muted">
                <h2 class="mb-1 font-medium text-gray-700 dark:text-gray-300">{{ t('history') }}</h2>
                <ul>
                    <li v-for="(entry, index) in history" :key="index">
                        {{ entry.at ? new Date(entry.at).toLocaleString() : '' }} — {{ entry.user ?? t('system') }}: {{ t(`audit_${entry.action}`) }}<span v-if="entry.target"> → {{ entry.target }}</span>
                    </li>
                </ul>
            </section>
        </main>
    </div>
</template>
