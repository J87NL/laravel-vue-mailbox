<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import type { MailboxFolder } from '../types'

defineProps<{
    folders: MailboxFolder[]
    activeId?: number
}>()
</script>

<template>
    <nav class="flex flex-col gap-0.5 text-sm">
        <Link
            v-for="folder in folders"
            :key="folder.id"
            :href="folder.url"
            class="flex items-center justify-between rounded-md px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-800"
            :class="{ 'bg-gray-100 font-medium dark:bg-gray-800': folder.id === activeId }"
            :style="{ paddingLeft: `${0.5 + folder.depth * 0.75}rem` }"
        >
            <span class="truncate">{{ folder.name }}</span>
            <span v-if="folder.unseen > 0" class="ml-2 text-xs tabular-nums text-gray-500">{{ folder.unseen }}</span>
        </Link>
    </nav>
</template>
