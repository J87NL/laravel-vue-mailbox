import { router } from '@inertiajs/vue3'
import { onBeforeUnmount, onMounted } from 'vue'

export interface MessageHotkeysOptions {
    next: () => string | null
    previous: () => string | null
}

const TYPING_TAGS = ['INPUT', 'SELECT', 'TEXTAREA']

function ignoredTarget(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) {
        return false
    }

    if (target.isContentEditable || TYPING_TAGS.includes(target.tagName)) {
        return true
    }

    return target.closest('[aria-modal="true"]') !== null
}

function ignoredEvent(event: KeyboardEvent): boolean {
    return event.defaultPrevented
        || event.repeat
        || event.isComposing
        || event.altKey
        || event.ctrlKey
        || event.metaKey
        || event.shiftKey
        || ignoredTarget(event.target)
}

export function useMessageHotkeys(options: MessageHotkeysOptions): void {
    function urlFor(key: string): string | null {
        if (key === 'j') {
            return options.next()
        }

        if (key === 'k') {
            return options.previous()
        }

        return null
    }

    function onKeydown(event: KeyboardEvent): void {
        if (ignoredEvent(event)) {
            return
        }

        const url = urlFor(event.key.toLowerCase())

        if (!url) {
            return
        }

        event.preventDefault()
        router.visit(url)
    }

    onMounted(() => window.addEventListener('keydown', onKeydown))
    onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
}
