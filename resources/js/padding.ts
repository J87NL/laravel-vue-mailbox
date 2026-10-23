import { inject } from 'vue'

export const MAILBOX_PAGE_PADDING = 'pagePadding'

export function useMailboxPagePadding(): boolean {
    return inject<boolean>(MAILBOX_PAGE_PADDING, true)
}
