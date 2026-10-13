import { useTranslations } from '@table-builder/composables/useTranslations'

export const MAILBOX_TRANSLATIONS = 'vue_mailbox_translations'

export function useMailboxTranslations() {
    return useTranslations(MAILBOX_TRANSLATIONS, { group: 'mailbox' })
}
