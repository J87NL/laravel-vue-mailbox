<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { nextTick, onBeforeUnmount, ref } from 'vue'
import FolderList from '@mailbox/components/FolderList.vue'
import MessageActions from '@mailbox/components/MessageActions.vue'
import MessageFrame from '@mailbox/components/MessageFrame.vue'
import type { AuditEntry, ConversationEntry, MailAddress, MailboxAttachment, MailboxFolder, MailboxSummary } from '@mailbox/types'
import { useMailboxTranslations } from '@mailbox/translations'
import { ui } from '@mailbox/ui'

defineProps<{
    actionsUrl: string | null
    attachments: MailboxAttachment[]
    backUrl: string
    composeUrl: string | null
    contactUrl: string | null
    conversation?: ConversationEntry[]
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

const dialog = ref<HTMLElement | null>(null)
const fullscreen = ref(false)

let opener: HTMLElement | null = null

async function openFullscreen(event: MouseEvent): Promise<void> {
    opener = event.currentTarget as HTMLElement
    fullscreen.value = true
    document.body.style.overflow = 'hidden'

    await nextTick()

    dialog.value?.focus()
}

function closeFullscreen(): void {
    if (!fullscreen.value) {
        return
    }

    fullscreen.value = false
    document.body.style.overflow = ''
    opener?.focus()
}

onBeforeUnmount(() => {
    if (fullscreen.value) {
        document.body.style.overflow = ''
    }
})

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
                <MessageActions
                    :actions-url="actionsUrl"
                    :compose-url="composeUrl"
                    :contact-url="contactUrl"
                    :folders="folders"
                    :message="message"
                />
                <button type="button" :class="ui.button" @click="openFullscreen">{{ t('open_fullscreen') }}</button>
            </div>

            <nav v-if="conversation?.length" :class="[ui.panel, 'p-0']" :aria-label="t('conversation')">
                <h2 class="border-b border-gray-200 px-4 py-2 text-sm font-medium dark:border-gray-700">{{ t('conversation') }} ({{ conversation.length }})</h2>
                <ol class="divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <li v-for="entry in conversation" :key="entry.id">
                        <span v-if="entry.current" class="flex flex-wrap gap-x-3 px-4 py-2 font-semibold" aria-current="true">
                            <span class="min-w-0 flex-1 truncate">{{ entry.from }}</span>
                            <span :class="ui.muted">{{ entry.folder }}</span>
                            <span :class="ui.muted">{{ entry.sent_at ? new Date(entry.sent_at).toLocaleString() : '' }}</span>
                        </span>
                        <Link v-else :href="entry.url" class="flex flex-wrap gap-x-3 px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-800">
                            <span class="min-w-0 flex-1 truncate">{{ entry.from }}</span>
                            <span :class="ui.muted">{{ entry.folder }}</span>
                            <span :class="ui.muted">{{ entry.sent_at ? new Date(entry.sent_at).toLocaleString() : '' }}</span>
                        </Link>
                    </li>
                </ol>
            </nav>

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

            <Teleport to="body">
                <div
                    v-if="fullscreen"
                    ref="dialog"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="message.subject ?? t('no_subject')"
                    tabindex="-1"
                    class="fixed inset-0 z-40 flex flex-col gap-3 bg-white p-4 text-gray-900 focus:outline-none dark:bg-gray-900 dark:text-gray-100"
                    @keydown.esc.stop="closeFullscreen"
                >
                    <div class="flex items-start justify-between gap-4">
                        <h2 class="min-w-0 truncate text-lg font-semibold">{{ message.subject ?? t('no_subject') }}</h2>
                        <button type="button" :class="ui.button" @click="closeFullscreen">{{ t('close') }}</button>
                    </div>
                    <div :class="ui.toolbar">
                        <MessageActions
                            :actions-url="actionsUrl"
                            :compose-url="composeUrl"
                            :contact-url="contactUrl"
                            :folders="folders"
                            :message="message"
                        />
                    </div>
                    <MessageFrame :html="html" :image-origin="imageOrigin" :remote-images="remoteImages" fill @escape="closeFullscreen" />
                </div>
            </Teleport>

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
