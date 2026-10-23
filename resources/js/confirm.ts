import { inject } from 'vue'

export type MailboxConfirm = (message: string) => Promise<boolean>

export const MAILBOX_CONFIRM = 'confirm'

const decline: MailboxConfirm = () => Promise.resolve(false)

export function useMailboxConfirm(): MailboxConfirm {
    return inject<MailboxConfirm>(MAILBOX_CONFIRM, decline)
}
