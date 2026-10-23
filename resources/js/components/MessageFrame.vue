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
let measuredContent = 0
let measuredFrame = 0

const imageSources = computed(() => props.remoteImages ? '* data:' : `data: ${props.imageOrigin}`)

const srcdoc = computed(() => `<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src ${imageSources.value}; style-src 'unsafe-inline'; font-src data:">
<base target="_blank">
<style>body{margin:0;font-family:system-ui,sans-serif;font-size:14px;line-height:1.5;color:#111;word-wrap:break-word}img{max-width:100%;height:auto}</style>
</head>
<body>${props.html}<style>html,body{height:auto!important;min-height:0!important;max-height:none!important}</style></body>
</html>`)

function availableHeight(): number {
    if (!frame.value) {
        return 0
    }

    const top = frame.value.getBoundingClientRect().top + window.scrollY

    return window.innerHeight - top - 24
}

function resize(observed = false): void {
    const body = frame.value?.contentDocument?.body

    if (!body) {
        return
    }

    const content = body.scrollHeight
    const frameGrowth = height.value - measuredFrame
    const followsFrame = observed && frameGrowth > 0 && Math.abs(content - measuredContent - frameGrowth) <= 2

    measuredContent = content
    measuredFrame = height.value

    if (followsFrame) {
        return
    }

    height.value = Math.max(320, content + 24, availableHeight())
}

function onWindowResize(): void {
    resize()
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

    observer = new ResizeObserver(() => resize(true))
    observer.observe(body)
}

onMounted(() => {
    window.addEventListener('resize', onWindowResize)
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', onWindowResize)
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
