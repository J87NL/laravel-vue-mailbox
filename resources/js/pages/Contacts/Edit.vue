<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import Form from '@form-builder/components/Form.vue'
import type { FormSchema } from '@form-builder/types/form-builder'
import { useMailboxTranslations } from '@mailbox/translations'
import type { MailboxSummary } from '@mailbox/types'
import { ui } from '@mailbox/ui'

const props = defineProps<{
    backUrl: string
    destroyUrl: string | null
    form: FormSchema
    mailbox: MailboxSummary
    title: string
}>()

const { t } = useMailboxTranslations()

function destroy(): void {
    if (!props.destroyUrl || !confirm(t('contact_delete_confirm'))) {
        return
    }

    router.delete(props.destroyUrl)
}
</script>

<template>
    <Head :title="title" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">{{ title }}</h1>
                <p :class="ui.muted">{{ t('contacts') }} · {{ mailbox.name }}</p>
            </div>
            <div :class="ui.toolbar">
                <button v-if="destroyUrl" type="button" :class="ui.dangerButton" @click="destroy">{{ t('delete') }}</button>
                <Link :href="backUrl" :class="ui.button">{{ t('back') }}</Link>
            </div>
        </div>

        <section :class="ui.panel">
            <Form :schema="form" />
        </section>
    </div>
</template>
