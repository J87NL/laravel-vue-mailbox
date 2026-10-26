import { router, usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref, type ComputedRef } from 'vue'

export const MAILBOX_NOTIFICATIONS = 'vueMailboxNotifications'

export const MAILBOX_NOTIFICATIONS_STORAGE_KEY = 'vue-mailbox.notifications'

export const MAILBOX_NOTIFICATIONS_SUMMARY_AFTER = 3

export interface MailboxNotificationsConfig {
    labels: Record<string, string>
    poll_seconds: number
    url: string
}

export interface NewMailboxMessage {
    folder_id: number
    folder_url: string
    from_email: string | null
    from_name: string | null
    id: number
    mailbox_id: number
    mailbox_name: string
    sent_at: string | null
    subject: string | null
    url: string
}

export interface NewMailboxMessages {
    count: number
    cursor: number
    messages: NewMailboxMessage[]
}

export type MailboxNotificationState = 'unavailable' | 'blocked' | 'on' | 'off'

export interface MailboxNotificationsOptions {
    folderId?: () => number | null
}

const enabled = ref(false)
const permission = ref<NotificationPermission | 'unsupported'>('unsupported')
const config = ref<MailboxNotificationsConfig | null>(null)

let cursor: number | null = null
let lastPoll = 0
let polling = false
let timer: ReturnType<typeof setInterval> | null = null
let users = 0
let currentFolder: () => number | null = () => null

function supported(): boolean {
    return typeof window !== 'undefined' && 'Notification' in window
}

function currentPermission(): NotificationPermission | 'unsupported' {
    return supported() ? Notification.permission : 'unsupported'
}

function readPreference(): boolean {
    try {
        return window.localStorage.getItem(MAILBOX_NOTIFICATIONS_STORAGE_KEY) === 'on'
    } catch {
        return false
    }
}

function writePreference(on: boolean): void {
    try {
        window.localStorage.setItem(MAILBOX_NOTIFICATIONS_STORAGE_KEY, on ? 'on' : 'off')
    } catch {
        return
    }
}

function label(key: string, replacements: Record<string, string | number> = {}): string {
    let value = config.value?.labels[key] ?? key

    Object.entries(replacements).forEach(([name, replacement]) => {
        value = value.replace(new RegExp(`:${name}`, 'g'), String(replacement))
    })

    return value
}

function active(): boolean {
    permission.value = currentPermission()

    return config.value !== null && enabled.value && permission.value === 'granted'
}

function show(title: string, body: string, tag: string, url: string): void {
    const notification = new Notification(title, {
        body,
        tag,
    })

    notification.onclick = () => {
        window.focus()
        notification.close()
        router.visit(url)
    }
}

function announce(data: NewMailboxMessages): void {
    const looking = document.visibilityState === 'visible' && document.hasFocus()
    const folderId = currentFolder()
    const shown = data.messages.filter((message) => !(looking && message.folder_id === folderId))
    const total = data.count - (data.messages.length - shown.length)

    if (shown.length === 0) {
        return
    }

    if (total > MAILBOX_NOTIFICATIONS_SUMMARY_AFTER) {
        const mailboxes = [...new Set(shown.map((message) => message.mailbox_name))].join(', ')

        show(label('new_messages', { count: total }), mailboxes, 'vue-mailbox-summary', shown[0].folder_url)

        return
    }

    shown.slice().reverse().forEach((message) => {
        show(
            message.from_name || message.from_email || message.mailbox_name,
            `${message.subject || label('no_subject')}\n${message.mailbox_name}`,
            `vue-mailbox-${message.id}`,
            message.url,
        )
    })
}

async function poll(): Promise<void> {
    if (!active() || polling || !config.value) {
        return
    }

    polling = true
    lastPoll = Date.now()

    try {
        const url = new URL(config.value.url, window.location.origin)

        if (cursor !== null) {
            url.searchParams.set('after', String(cursor))
        }

        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })

        if (!response.ok) {
            return
        }

        const data = (await response.json()) as NewMailboxMessages
        const first = cursor === null

        cursor = data.cursor

        if (first || !active()) {
            return
        }

        announce(data)
    } catch {
        return
    } finally {
        polling = false
    }
}

function start(): void {
    if (timer !== null || !active() || !config.value) {
        return
    }

    const interval = config.value.poll_seconds * 1000

    timer = setInterval(poll, interval)

    if (cursor === null || Date.now() - lastPoll >= interval) {
        void poll()
    }
}

function stop(): void {
    if (timer === null) {
        return
    }

    clearInterval(timer)
    timer = null
}

async function enable(): Promise<void> {
    if (!supported()) {
        return
    }

    const result = Notification.permission === 'default' ? await Notification.requestPermission() : Notification.permission

    permission.value = result

    if (result !== 'granted') {
        return
    }

    enabled.value = true
    writePreference(true)
    start()
}

function disable(): void {
    enabled.value = false
    writePreference(false)
    stop()
    cursor = null
}

export function useMailboxNotifications(options: MailboxNotificationsOptions = {}) {
    const page = usePage()

    const settings = computed(() => ((page.props as Record<string, unknown>)[MAILBOX_NOTIFICATIONS] as MailboxNotificationsConfig | null | undefined) ?? null)

    const state: ComputedRef<MailboxNotificationState> = computed(() => {
        if (!settings.value || permission.value === 'unsupported') {
            return 'unavailable'
        }

        if (permission.value === 'denied') {
            return 'blocked'
        }

        return enabled.value && permission.value === 'granted' ? 'on' : 'off'
    })

    const buttonLabel = computed(() => {
        if (state.value === 'blocked') {
            return label('blocked')
        }

        return state.value === 'on' ? label('disable') : label('enable')
    })

    const hint = computed(() => {
        if (state.value === 'blocked') {
            return label('blocked_hint')
        }

        return state.value === 'on' ? label('on_hint') : label('off_hint')
    })

    async function toggle(): Promise<void> {
        if (state.value === 'on') {
            disable()

            return
        }

        await enable()
    }

    onMounted(() => {
        users++
        config.value = settings.value
        enabled.value = readPreference()
        permission.value = currentPermission()

        if (options.folderId) {
            currentFolder = options.folderId
        }

        if (!active()) {
            stop()

            return
        }

        start()
    })

    onBeforeUnmount(() => {
        users--

        if (options.folderId && currentFolder === options.folderId) {
            currentFolder = () => null
        }

        if (users > 0) {
            return
        }

        stop()
    })

    return {
        hint,
        label: buttonLabel,
        state,
        toggle,
    }
}
