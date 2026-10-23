<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

const props = withDefaults(defineProps<{
    html: string
    imageOrigin: string
    remoteImages: boolean
    fill?: boolean
}>(), {
    fill: false,
})

const emit = defineEmits<{
    escape: []
}>()

const frame = ref<HTMLIFrameElement | null>(null)
const height = ref(400)

let observer: ResizeObserver | null = null

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

function availableHeight(): number {
    if (!frame.value) {
        return 0
    }

    const top = frame.value.getBoundingClientRect().top + window.scrollY

    return window.innerHeight - top - 24
}

function resize(): void {
    const body = frame.value?.contentDocument?.body

    if (!body) {
        return
    }

    height.value = Math.max(320, body.scrollHeight + 24, availableHeight())
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        emit('escape')
    }
}

function observe(): void {
    frame.value?.contentDocument?.addEventListener('keydown', onKeydown)
    observer?.disconnect()
    observer = null

    if (props.fill) {
        return
    }

    resize()

    const body = frame.value?.contentDocument?.body

    if (!body || typeof ResizeObserver === 'undefined') {
        return
    }

    observer = new ResizeObserver(() => resize())
    observer.observe(body)
}

onMounted(() => {
    window.addEventListener('resize', resize)
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', resize)
    observer?.disconnect()
})
</script>

<template>
    <iframe
        ref="frame"
        :srcdoc="srcdoc"
        sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
        referrerpolicy="no-referrer"
        class="w-full rounded-md border-0 bg-white"
        :class="{ 'h-full min-h-0 flex-1': fill }"
        :style="fill ? undefined : { height: `${height}px` }"
        title="Message"
        @load="observe"
    />
</template>
