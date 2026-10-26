<script setup lang="ts">
import { useMailboxNotifications } from '@mailbox/notifications'
import { ui } from '@mailbox/ui'

const props = defineProps<{
    folderId?: number | null
    mailboxId?: number | null
}>()

const { hint, label, state, toggle } = useMailboxNotifications({
    folderId: () => props.folderId ?? null,
    mailboxId: () => props.mailboxId ?? null,
})
</script>

<template>
    <button
        v-if="state !== 'unavailable'"
        type="button"
        :class="ui.button"
        :aria-pressed="state === 'on'"
        :disabled="state === 'blocked'"
        :title="hint"
        @click="toggle"
    >
        {{ label }}
    </button>
</template>
