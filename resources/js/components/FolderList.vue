<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { useMailboxTranslations } from '../translations'
import type { MailboxFolder } from '../types'

defineProps<{
    folders: MailboxFolder[]
    activeId?: number
}>()

const { t } = useMailboxTranslations()
</script>

<template>
    <nav class="flex flex-col gap-0.5 text-sm">
        <Link
            v-for="folder in folders"
            :key="folder.id"
            :href="folder.url"
            class="flex items-center justify-between rounded-md px-2 py-1.5 transition-colors"
            :class="
                folder.id === activeId
                    ? 'bg-gray-900/10 font-medium text-gray-900 dark:bg-white/15 dark:text-white'
                    : 'text-gray-700 hover:bg-gray-900/5 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-white/10 dark:hover:text-white'
            "
            :title="folder.syncs ? undefined : t('not_synced_hint')"
            :style="{ paddingLeft: `${0.5 + folder.depth * 0.75}rem` }"
        >
            <span class="truncate" :class="{ 'text-gray-500 dark:text-gray-400': !folder.syncs }">{{ folder.label }}</span>
            <span v-if="!folder.syncs" class="ml-2 shrink-0 rounded bg-gray-100 px-1.5 text-[10px] uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">{{ t('not_synced') }}</span>
            <span v-if="folder.unseen > 0" class="ml-2 text-xs tabular-nums text-gray-500">{{ folder.unseen }}</span>
        </Link>
    </nav>
</template>
