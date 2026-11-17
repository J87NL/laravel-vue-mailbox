import { ref, type Ref } from 'vue'

export const MAILBOX_OPEN_NEXT_STORAGE_KEY = 'vue-mailbox.open-next-after-archive'

const openNext = ref(false)

let loaded = false

function readPreference(): boolean {
    try {
        return window.localStorage.getItem(MAILBOX_OPEN_NEXT_STORAGE_KEY) === 'on'
    } catch {
        return false
    }
}

function writePreference(value: boolean): void {
    try {
        window.localStorage.setItem(MAILBOX_OPEN_NEXT_STORAGE_KEY, value ? 'on' : 'off')
    } catch {
        return
    }
}

export function useOpenNextAfterArchive(): { openNext: Ref<boolean>, setOpenNext: (value: boolean) => void } {
    if (!loaded && typeof window !== 'undefined') {
        openNext.value = readPreference()
        loaded = true
    }

    function setOpenNext(value: boolean): void {
        openNext.value = value
        writePreference(value)
    }

    return { openNext, setOpenNext }
}
