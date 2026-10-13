export interface MailboxFolder {
    id: number
    label: string
    name: string
    path: string
    role: 'inbox' | 'drafts' | 'sent' | 'archive' | 'junk' | 'trash' | 'custom'
    depth: number
    unseen: number
    url: string
}

export interface MailboxSummary {
    id: number
    name: string
    email: string
    url?: string
    synced_at?: string | null
}

export interface MailAddress {
    email: string
    name: string
}

export interface MailboxAttachment {
    filename: string | null
    content_type: string
    content_id: string | null
    inline: boolean
    url: string
}

export interface AuditEntry {
    action: string
    at: string | null
    target: string | null
    user: string | null
}
