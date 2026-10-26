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
    mailboxes: number[]
    messages: NewMailboxMessage[]
}

export type MailboxNotificationState = 'unavailable' | 'blocked' | 'on' | 'off'

export interface MailboxNotificationsOptions {
    folderId?: () => number | null
    mailboxId?: () => number | null
}

interface MailboxNotificationsPreference {
    all: boolean
    excluded: number[]
    mailboxes: number[]
}

const allMailboxes = ref(false)
const enabledMailboxes = ref<number[]>([])
const excludedMailboxes = ref<number[]>([])
const permission = ref<NotificationPermission | 'unsupported'>('unsupported')
const config = ref<MailboxNotificationsConfig | null>(null)

let cursor: number | null = null
let loaded = false
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

function readPreference(): MailboxNotificationsPreference {
    const none: MailboxNotificationsPreference = { all: false, excluded: [], mailboxes: [] }

    try {
        const value = window.localStorage.getItem(MAILBOX_NOTIFICATIONS_STORAGE_KEY)

        if (value === 'on') {
            return { all: true, excluded: [], mailboxes: [] }
        }

        if (!value || value === 'off') {
            return none
        }

        const parsed: unknown = JSON.parse(value)

        if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
            return none
        }

        const entries = Object.entries(parsed as Record<string, unknown>)
        const ids = (on: boolean): number[] => entries
            .filter(([id, value]) => id !== '*' && value === on)
            .map(([id]) => Number(id))
            .filter((id) => Number.isInteger(id) && id > 0)

        return {
            all: (parsed as Record<string, unknown>)['*'] === true,
            excluded: ids(false),
            mailboxes: ids(true),
        }
    } catch {
        return none
    }
}

function writePreference(): void {
    try {
        if (allMailboxes.value) {
            window.localStorage.setItem(
                MAILBOX_NOTIFICATIONS_STORAGE_KEY,
                JSON.stringify({ '*': true, ...Object.fromEntries(excludedMailboxes.value.map((id) => [String(id), false])) }),
            )

            return
        }

        window.localStorage.setItem(
            MAILBOX_NOTIFICATIONS_STORAGE_KEY,
            JSON.stringify(Object.fromEntries(enabledMailboxes.value.map((id) => [String(id), true]))),
        )
    } catch {
        return
    }
}

function anyEnabled(): boolean {
    return allMailboxes.value || enabledMailboxes.value.length > 0
}

function enabledFor(mailboxId: number | null): boolean {
    if (mailboxId === null) {
        return anyEnabled()
    }

    if (allMailboxes.value) {
        return !excludedMailboxes.value.includes(mailboxId)
    }

    return enabledMailboxes.value.includes(mailboxId)
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

    return config.value !== null && anyEnabled() && permission.value === 'granted'
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
    const shown = data.messages.filter((message) => enabledFor(message.mailbox_id) && !(looking && message.folder_id === folderId))
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

        const requestedAll = allMailboxes.value

        if (cursor !== null) {
            url.searchParams.set('after', String(cursor))
        }

        if (!requestedAll) {
            enabledMailboxes.value.forEach((id) => url.searchParams.append('mailboxes[]', String(id)))
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

        if (requestedAll && allMailboxes.value) {
            allMailboxes.value = false
            enabledMailboxes.value = data.mailboxes.filter((id) => !excludedMailboxes.value.includes(id))
            excludedMailboxes.value = []
            writePreference()
        }

        if (!anyEnabled()) {
            stop()
            cursor = null

            return
        }

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

async function enable(mailboxId: number | null): Promise<void> {
    if (!supported()) {
        return
    }

    const result = Notification.permission === 'default' ? await Notification.requestPermission() : Notification.permission

    permission.value = result

    if (result !== 'granted') {
        return
    }

    if (mailboxId === null) {
        allMailboxes.value = true
        excludedMailboxes.value = []
    }

    if (mailboxId !== null) {
        excludedMailboxes.value = excludedMailboxes.value.filter((id) => id !== mailboxId)
    }

    if (mailboxId !== null && !allMailboxes.value && !enabledMailboxes.value.includes(mailboxId)) {
        enabledMailboxes.value = [...enabledMailboxes.value, mailboxId]
    }

    writePreference()
    start()
}

function disable(mailboxId: number | null): void {
    if (mailboxId === null) {
        allMailboxes.value = false
        enabledMailboxes.value = []
        excludedMailboxes.value = []
    }

    if (mailboxId !== null && allMailboxes.value) {
        excludedMailboxes.value = [...excludedMailboxes.value, mailboxId]
        writePreference()

        return
    }

    enabledMailboxes.value = enabledMailboxes.value.filter((id) => id !== mailboxId)
    writePreference()

    if (anyEnabled()) {
        return
    }

    stop()
    cursor = null
}

export function useMailboxNotifications(options: MailboxNotificationsOptions = {}) {
    const page = usePage()

    const mailboxId = computed(() => options.mailboxId?.() ?? null)

    const settings = computed(() => ((page.props as Record<string, unknown>)[MAILBOX_NOTIFICATIONS] as MailboxNotificationsConfig | null | undefined) ?? null)

    const state: ComputedRef<MailboxNotificationState> = computed(() => {
        if (!settings.value || permission.value === 'unsupported') {
            return 'unavailable'
        }

        if (permission.value === 'denied') {
            return 'blocked'
        }

        return enabledFor(mailboxId.value) && permission.value === 'granted' ? 'on' : 'off'
    })

    const buttonLabel = computed(() => {
        if (state.value === 'blocked') {
            return label('blocked')
        }

        const suffix = mailboxId.value === null ? '' : '_mailbox'

        return state.value === 'on' ? label(`disable${suffix}`) : label(`enable${suffix}`)
    })

    const hint = computed(() => {
        if (state.value === 'blocked') {
            return label('blocked_hint')
        }

        return state.value === 'on' ? label('on_hint') : label('off_hint')
    })

    async function toggle(): Promise<void> {
        if (state.value === 'on') {
            disable(mailboxId.value)

            return
        }

        await enable(mailboxId.value)
    }

    onMounted(() => {
        users++
        config.value = settings.value
        if (!loaded) {
            const preference = readPreference()

            loaded = true
            allMailboxes.value = preference.all
            excludedMailboxes.value = preference.excluded
            enabledMailboxes.value = preference.mailboxes
        }
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
