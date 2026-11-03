<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Form from '@form-builder/components/Form.vue'
import type { FormSchema } from '@form-builder/types/form-builder'
import FolderList from '@mailbox/components/FolderList.vue'
import { useMailboxTranslations } from '@mailbox/translations'
import type { MailboxFolder, MailboxSummary, MailboxTemplate } from '@mailbox/types'
import { ui } from '@mailbox/ui'

const props = withDefaults(defineProps<{
    backUrl: string
    folders: MailboxFolder[]
    form: FormSchema
    mailbox: MailboxSummary
    mode: 'new' | 'reply' | 'reply_all' | 'forward'
    templates?: MailboxTemplate[]
}>(), {
    templates: () => [],
})

const { t } = useMailboxTranslations()

const title = computed(() => t(props.mode === 'new' ? 'compose' : props.mode))

const composer = ref<InstanceType<typeof Form> | null>(null)

const chosenTemplate = ref<number | ''>('')

const insertingTemplate = ref(false)

const templateFailed = ref(false)

const EMPTY_FIRST_PARAGRAPH = /^\s*<p>(?:\s|&nbsp;|<br\s*\/?>)*<\/p>/

function firstRecipient(value: unknown): string | null {
    const first = Array.isArray(value) ? value[0] : String(value ?? '').split(',')[0]
    const entry = String(first ?? '').trim()

    if (entry === '') {
        return null
    }

    return entry.match(/<([^>]+)>/)?.[1] ?? entry
}

function withTemplate(template: string, body: string): string {
    if (EMPTY_FIRST_PARAGRAPH.test(body)) {
        return body.replace(EMPTY_FIRST_PARAGRAPH, template)
    }

    return `${template}${body}`
}

async function insertTemplate(): Promise<void> {
    const template = props.templates.find((item) => item.id === chosenTemplate.value)
    const form = composer.value?.form

    if (!template || !form || insertingTemplate.value) {
        return
    }

    const url = new URL(template.url, window.location.origin)
    const recipient = firstRecipient(form.to)
    const subject = String(form.subject ?? '').trim()

    if (recipient) {
        url.searchParams.set('to', recipient)
    }

    if (subject !== '') {
        url.searchParams.set('subject', subject)
    }

    insertingTemplate.value = true
    templateFailed.value = false

    try {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })

        if (!response.ok) {
            templateFailed.value = true

            return
        }

        const rendered = await response.json() as { body: string; subject: string | null }

        form.body = withTemplate(rendered.body, String(form.body ?? ''))

        if (rendered.subject && subject === '') {
            form.subject = rendered.subject
        }
    } catch {
        templateFailed.value = true
    } finally {
        insertingTemplate.value = false
        chosenTemplate.value = ''
    }
}
</script>

<template>
    <Head :title="title" />

    <div class="flex h-full flex-1 gap-4 p-4">
        <aside class="hidden w-60 shrink-0 md:block">
            <FolderList :folders="folders" />
        </aside>

        <main class="flex min-w-0 flex-1 flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h1 class="text-2xl font-semibold">{{ title }}</h1>
                    <p :class="ui.muted">{{ t('sent_from', { email: mailbox.email }) }}</p>
                </div>
                <div :class="ui.toolbar">
                    <select
                        v-if="templates.length"
                        v-model="chosenTemplate"
                        :class="ui.select"
                        :aria-label="t('choose_template')"
                        :disabled="insertingTemplate"
                        @change="insertTemplate"
                    >
                        <option value="">{{ t('choose_template') }}</option>
                        <option v-for="template in templates" :key="template.id" :value="template.id">{{ template.name }}</option>
                    </select>
                    <Link :href="backUrl" :class="ui.button">{{ t('cancel') }}</Link>
                </div>
            </div>

            <p v-if="templateFailed" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-800 dark:bg-red-950 dark:text-red-200" role="alert">
                {{ t('template_insert_failed') }}
            </p>

            <div :class="ui.panel">
                <Form ref="composer" :schema="form" />
            </div>
        </main>
    </div>
</template>
