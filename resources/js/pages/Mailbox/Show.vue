<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import FolderList from '@mailbox/components/FolderList.vue'
import MessageFrame from '@mailbox/components/MessageFrame.vue'
import type { AuditEntry, MailAddress, MailboxAttachment, MailboxFolder, MailboxSummary } from '@mailbox/types'

const props = defineProps<{
    actionsUrl: string | null
    attachments: MailboxAttachment[]
    backUrl: string
    composeUrl: string | null
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

const moveTarget = ref<number | ''>('')

function act(action: string, extra: Record<string, unknown> = {}): void {
    if (!props.actionsUrl) {
        return
    }

    if (action === 'delete' && !confirm('Dit bericht verwijderen?')) {
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

function replyUrl(mode: string): string {
    return `${props.composeUrl}?reply=${props.message.id}&mode=${mode}`
}

function addresses(list: MailAddress[] | null): string {
    return (list ?? []).map((address) => address.name ? `${address.name} <${address.email}>` : address.email).join(', ')
}
</script>

<template>
    <Head :title="message.subject ?? '(geen onderwerp)'" />

    <div class="flex h-full flex-1 gap-4 p-4">
        <aside class="hidden w-60 shrink-0 md:block">
            <FolderList :folders="folders" />
        </aside>

        <main class="flex min-w-0 flex-1 flex-col gap-4">
            <div class="flex flex-wrap items-center gap-2">
                <Link :href="backUrl" class="btn">Terug</Link>
                <template v-if="composeUrl">
                    <Link :href="replyUrl('reply')" class="btn">Beantwoorden</Link>
                    <Link :href="replyUrl('reply_all')" class="btn">Allen beantwoorden</Link>
                    <Link :href="replyUrl('forward')" class="btn">Doorsturen</Link>
                </template>
                <template v-if="actionsUrl">
                    <button type="button" class="btn" @click="act('archive')">Archiveren</button>
                    <button type="button" class="btn btn-danger" @click="act('delete')">Verwijderen</button>
                    <button type="button" class="btn" @click="act('unread')">Ongelezen</button>
                    <button type="button" class="btn" @click="act(message.is_flagged ? 'unflag' : 'flag')">
                        {{ message.is_flagged ? 'Markering weg' : 'Markeren' }}
                    </button>
                    <select v-model="moveTarget" class="rounded-md border px-2 py-1 text-sm" @change="move">
                        <option value="">Verplaatsen naar…</option>
                        <option v-for="folder in folders" :key="folder.id" :value="folder.id">{{ folder.path }}</option>
                    </select>
                </template>
            </div>

            <article class="rounded-lg border bg-white p-4 dark:bg-gray-900">
                <header class="mb-4 border-b pb-3">
                    <h1 class="text-xl font-semibold">{{ message.subject ?? '(geen onderwerp)' }}</h1>
                    <dl class="mt-2 grid grid-cols-[4rem_1fr] gap-x-2 text-sm text-gray-600 dark:text-gray-300">
                        <dt>Van</dt>
                        <dd>{{ message.from_name ? `${message.from_name} <${message.from_email}>` : message.from_email }}</dd>
                        <dt>Aan</dt>
                        <dd>{{ addresses(message.to) }}</dd>
                        <template v-if="message.cc?.length">
                            <dt>Cc</dt>
                            <dd>{{ addresses(message.cc) }}</dd>
                        </template>
                        <dt>Datum</dt>
                        <dd>{{ message.sent_at ? new Date(message.sent_at).toLocaleString() : '' }}</dd>
                    </dl>
                </header>

                <p v-if="hasRemoteImages" class="mb-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    Externe afbeeldingen zijn verborgen om je privacy te beschermen.
                    <Link href="?images=1" class="underline" preserve-scroll>Afbeeldingen tonen</Link>
                </p>

                <MessageFrame :html="html" :image-origin="imageOrigin" :remote-images="remoteImages" />

                <ul v-if="attachments.length" class="mt-4 flex flex-wrap gap-2">
                    <li v-for="attachment in attachments" :key="attachment.url">
                        <a :href="attachment.url" class="btn text-sm">{{ attachment.filename ?? 'bijlage' }}</a>
                    </li>
                </ul>
            </article>

            <section v-if="history.length" class="text-sm text-gray-500">
                <h2 class="mb-1 font-medium text-gray-700 dark:text-gray-300">Geschiedenis</h2>
                <ul>
                    <li v-for="(entry, index) in history" :key="index">
                        {{ entry.at ? new Date(entry.at).toLocaleString() : '' }} — {{ entry.user ?? 'systeem' }}: {{ entry.action }}<span v-if="entry.target"> → {{ entry.target }}</span>
                    </li>
                </ul>
            </section>
        </main>
    </div>
</template>
