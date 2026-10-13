<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import Form from '@form-builder/components/Form.vue'
import type { FormSchema } from '@form-builder/types/form-builder'
import TableBuilder from '@table-builder/components/TableBuilder.vue'
import type { TableData } from '@table-builder/types/table-builder'
import { ui } from '@mailbox/ui'

const props = defineProps<{
    accessForm: FormSchema | null
    accessTable: TableData | null
    backUrl: string
    destroyUrl: string | null
    form: FormSchema
    title: string
}>()

const page = usePage()
const accessError = computed(() => (page.props.errors as Record<string, string> | undefined)?.accessor ?? null)

function destroy(): void {
    if (!props.destroyUrl || !confirm('Deze mailbox en alle gesynchroniseerde berichten verwijderen? De mail op de server blijft staan.')) {
        return
    }

    router.delete(props.destroyUrl)
}
</script>

<template>
    <Head :title="title" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">{{ title }}</h1>
            <div :class="ui.toolbar">
                <button v-if="destroyUrl" type="button" :class="ui.dangerButton" @click="destroy">Verwijderen</button>
                <Link :href="backUrl" :class="ui.button">Terug</Link>
            </div>
        </div>

        <section :class="ui.panel">
            <Form :schema="form" />
        </section>

        <section v-if="accessTable && accessForm" :class="ui.panel">
            <h2 class="mb-1 text-lg font-semibold">Toegang</h2>
            <p :class="[ui.muted, 'mb-4']">
                Lezen: berichten bekijken. Lezen en werken: ook versturen, verplaatsen, archiveren en verwijderen. Beheren: ook deze instellingen en toegang.
            </p>
            <p v-if="accessError" class="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">{{ accessError }}</p>
            <TableBuilder :table="accessTable" />
            <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                <Form :schema="accessForm" />
            </div>
        </section>
    </div>
</template>
