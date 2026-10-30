const base = 'inline-flex items-center justify-center gap-1.5 rounded-md border px-3 py-1.5 text-sm font-medium shadow-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-50'

export interface MailboxUi {
    button: string
    primaryButton: string
    dangerButton: string
    select: string
    panel: string
    toolbar: string
    muted: string
}

export const ui: MailboxUi = {
    button: `${base} border-gray-300 bg-white text-gray-800 hover:bg-gray-50 focus-visible:ring-gray-400 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700`,
    primaryButton: `${base} border-transparent bg-gray-900 text-white hover:bg-gray-700 focus-visible:ring-gray-500 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-300`,
    dangerButton: `${base} border-red-300 bg-white text-red-700 hover:bg-red-50 focus-visible:ring-red-400 dark:border-red-800 dark:bg-gray-800 dark:text-red-300 dark:hover:bg-red-950`,
    select: 'rounded-md border border-gray-300 bg-white py-1.5 pl-2 pr-8 text-sm text-gray-800 shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100',
    panel: 'rounded-lg border border-gray-200 bg-white p-4 text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100',
    toolbar: 'flex flex-wrap items-center gap-2',
    muted: 'text-sm text-gray-500 dark:text-gray-400',
}

export function configureMailboxUi(overrides: Partial<MailboxUi>): void {
    for (const key of Object.keys(overrides) as (keyof MailboxUi)[]) {
        const classes = overrides[key]

        if (typeof classes === 'string') {
            ui[key] = classes
        }
    }
}
