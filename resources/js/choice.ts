import { inject } from 'vue'

export interface MailboxChoiceOption {
    value: string
    label: string
    destructive?: boolean
}

export interface MailboxChoiceOptions {
    title: string
    choices: MailboxChoiceOption[]
}

export type MailboxChoice = (options: MailboxChoiceOptions) => Promise<string | null>

export const MAILBOX_CHOICE = 'mailboxChoice'

export function useMailboxChoice(): MailboxChoice | null {
    return inject<MailboxChoice | null>(MAILBOX_CHOICE, null)
}
