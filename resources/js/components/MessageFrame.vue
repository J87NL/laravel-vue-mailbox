<script setup lang="ts">
import { computed, ref } from 'vue'

const props = defineProps<{
    html: string
    imageOrigin: string
    remoteImages: boolean
}>()

const frame = ref<HTMLIFrameElement | null>(null)
const height = ref(400)

const imageSources = computed(() => props.remoteImages ? '* data:' : `data: ${props.imageOrigin}`)

const srcdoc = computed(() => `<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src ${imageSources.value}; style-src 'unsafe-inline'; font-src data:">
<base target="_blank">
<style>body{margin:0;font-family:system-ui,sans-serif;font-size:14px;line-height:1.5;color:#111;word-wrap:break-word}img{max-width:100%;height:auto}</style>
</head>
<body>${props.html}</body>
</html>`)

function resize(): void {
    const body = frame.value?.contentDocument?.body

    if (!body) {
        return
    }

    height.value = Math.max(200, body.scrollHeight + 24)
}
</script>

<template>
    <iframe
        ref="frame"
        :srcdoc="srcdoc"
        sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
        referrerpolicy="no-referrer"
        class="w-full rounded-md border-0 bg-white"
        :style="{ height: `${height}px` }"
        title="Message"
        @load="resize"
    />
</template>
