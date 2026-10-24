<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import type { MailboxFolder } from '../types'
import { useMailboxConfirm } from '../confirm'
import { useMailboxTranslations } from '../translations'
import { ui } from '../ui'

const props = defineProps<{
    actionsUrl: string | null
    composeUrl: string | null
    contactUrl: string | null
    folders: MailboxFolder[]
    message: {
        id: number
        is_flagged: boolean
    }
}>()

const { t } = useMailboxTranslations()
const confirm = useMailboxConfirm()

const moveTarget = ref<number | ''>('')

async function act(action: string, extra: Record<string, unknown> = {}): Promise<void> {
    if (!props.actionsUrl) {
        return
    }

    if (action === 'delete' && !(await confirm(t('message_delete_confirm')))) {
        return
    }

    router.post(props.actionsUrl, { action, ids: [props.message.id], ...extra }, { preserveScroll: true, preserveState: true })
}

function move(): void {
    if (moveTarget.value === '') {
        return
    }

    void act('move', { folder_id: moveTarget.value })
}

function addContact(): void {
    if (props.contactUrl) {
        router.post(props.contactUrl, {}, { preserveScroll: true })
    }
}

function replyUrl(mode: string): string {
    return `${props.composeUrl}?reply=${props.message.id}&mode=${mode}`
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
            <button type="button" :class="ui.dangerButton" @click="act('delete')">{{ t('delete') }}</button>
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
