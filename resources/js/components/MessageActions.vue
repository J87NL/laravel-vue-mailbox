<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import type { MailboxFolder } from '../types'
import { useMailboxChoice } from '../choice'
import { useMailboxConfirm } from '../confirm'
import { useMailboxTranslations } from '../translations'
import { ui } from '../ui'

const props = defineProps<{
    actionsUrl: string | null
    composeUrl: string | null
    confirmDelete?: boolean
    contactUrl: string | null
    conversationCount?: number
    folderRole?: MailboxFolder['role']
    folders: MailboxFolder[]
    message: {
        id: number
        is_flagged: boolean
    }
    spamSuggested?: boolean
}>()

const { t } = useMailboxTranslations()
const confirm = useMailboxConfirm()
const choose = useMailboxChoice()

const moveTarget = ref<number | ''>('')

const junkAction = computed((): 'junk' | 'not_junk' | null => {
    if (props.folderRole === 'junk') {
        return 'not_junk'
    }

    if (props.folderRole === 'sent' || props.folderRole === 'drafts') {
        return null
    }

    return 'junk'
})

function act(action: string, extra: Record<string, unknown> = {}): void {
    if (!props.actionsUrl) {
        return
    }

    router.post(props.actionsUrl, { action, ids: [props.message.id], ...extra }, { preserveScroll: true, preserveState: true })
}

async function deleteAction(): Promise<string | null> {
    if (choose && (props.conversationCount ?? 0) > 1) {
        const choice = await choose({
            title: t('delete_conversation_title'),
            choices: [
                { value: 'delete', label: t('delete_this_message') },
                { value: 'delete_conversation', label: t('delete_whole_conversation'), destructive: true },
                { value: 'cancel', label: t('cancel') },
            ],
        })

        return choice === 'delete' || choice === 'delete_conversation' ? choice : null
    }

    if (props.confirmDelete && !(await confirm(t('message_delete_confirm')))) {
        return null
    }

    return 'delete'
}

async function remove(): Promise<void> {
    const action = await deleteAction()

    if (action) {
        act(action)
    }
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
    const url = new URL(props.composeUrl ?? '', window.location.origin)

    url.searchParams.set('reply', String(props.message.id))
    url.searchParams.set('mode', mode)

    return `${url.pathname}${url.search}`
}
</script>

<template>
    <div class="contents">
        <template v-if="composeUrl">
            <Link :href="replyUrl('reply')" :class="ui.primaryButton">{{ t('reply') }}</Link>
            <Link :href="replyUrl('reply_all')" :class="ui.button">{{ t('reply_all') }}</Link>
            <Link :href="replyUrl('forward')" :class="ui.button">{{ t('forward') }}</Link>
        </template>
        <template v-if="actionsUrl">
            <button type="button" :class="ui.button" @click="act('archive')">{{ t('archive') }}</button>
            <button v-if="junkAction" type="button" :class="ui.button" @click="act(junkAction)">{{ t(junkAction === 'junk' ? 'mark_junk' : 'mark_not_junk') }}</button>
            <button v-if="spamSuggested && junkAction === 'junk'" type="button" :class="ui.button" @click="act('not_junk')">{{ t('mark_not_junk') }}</button>
            <button type="button" :class="ui.dangerButton" @click="remove">{{ t('delete') }}</button>
            <button type="button" :class="ui.button" @click="act('unread')">{{ t('mark_unread') }}</button>
            <button type="button" :class="ui.button" @click="act(message.is_flagged ? 'unflag' : 'flag')">
                {{ message.is_flagged ? t('unflag') : t('flag') }}
            </button>
            <select v-model="moveTarget" :class="ui.select" :aria-label="t('move_placeholder')" @change="move">
                <option value="">{{ t('move_placeholder') }}</option>
                <option v-for="folder in folders" :key="folder.id" :value="folder.id">{{ "\u00a0".repeat(folder.depth * 2) }}{{ folder.label }}</option>
            </select>
        </template>
        <button v-if="contactUrl" type="button" :class="ui.button" @click="addContact">{{ t('add_contact') }}</button>
    </div>
</template>
