<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import type { ReportImage } from '../lib/reports'

const props = defineProps<{ images: ReportImage[] }>()
const activeIndex = ref(0)
const selected = computed(() => props.images[activeIndex.value])
const failed = reactive(new Set<number>())
const mainLoading = ref(true)
const dialog = ref<HTMLDialogElement | null>(null)
const opener = ref<HTMLButtonElement | null>(null)
const viewport = ref<HTMLDivElement | null>(null)
const zoomed = ref(false)
const lightboxLoading = ref(true)
const opened = ref(false)
let previousOverflow = ''
let returnFocus: HTMLElement | null = null
let pan: { pointer: number; x: number; y: number; left: number; top: number } | null = null

watch(() => selected.value?.id, () => {
  if (!selected.value) closeLightbox()
  mainLoading.value = true
  lightboxLoading.value = true
  zoomed.value = false
  pan = null
  viewport.value?.scrollTo(0, 0)
})

async function openLightbox() {
  if (!selected.value || mainLoading.value || failed.has(selected.value.id) || opened.value) return
  returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : opener.value
  previousOverflow = document.body.style.overflow
  document.body.style.overflow = 'hidden'
  zoomed.value = false
  lightboxLoading.value = true
  opened.value = true
  await nextTick()
  if (!opened.value || !dialog.value?.isConnected) return
  dialog.value?.showModal()
  dialog.value?.querySelector<HTMLButtonElement>('[data-close]')?.focus()
}

function restorePage() {
  if (!opened.value) return
  opened.value = false
  document.body.style.overflow = previousOverflow
  pan = null
  if (returnFocus?.isConnected) returnFocus.focus({ preventScroll: true })
  returnFocus = null
}

function closeLightbox() {
  dialog.value?.close()
  restorePage()
}

function switchPhoto(offset: number) {
  if (props.images.length > 1) activeIndex.value = (activeIndex.value + offset + props.images.length) % props.images.length
}

function lightboxKeys(event: KeyboardEvent) {
  if (event.key === 'Tab') {
    const controls = dialog.value?.querySelectorAll<HTMLElement>('button:not(:disabled), [tabindex="0"]')
    const first = controls?.[0]
    const last = controls?.[controls.length - 1]
    if (first && last && ((event.shiftKey && document.activeElement === first) || (!event.shiftKey && document.activeElement === last))) {
      event.preventDefault()
      const target = event.shiftKey ? last : first
      target.focus()
    }
    return
  }
  if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
    event.preventDefault()
    switchPhoto(event.key === 'ArrowLeft' ? -1 : 1)
  }
}

async function toggleZoom() {
  zoomed.value = !zoomed.value
  await nextTick()
  const area = viewport.value
  if (area) area.scrollTo((area.scrollWidth - area.clientWidth) / 2, (area.scrollHeight - area.clientHeight) / 2)
}

function startPan(event: PointerEvent) {
  if (!zoomed.value || event.pointerType !== 'mouse' || event.button !== 0 || !viewport.value) return
  event.preventDefault()
  viewport.value.setPointerCapture(event.pointerId)
  pan = { pointer: event.pointerId, x: event.clientX, y: event.clientY, left: viewport.value.scrollLeft, top: viewport.value.scrollTop }
}

function movePan(event: PointerEvent) {
  if (!pan || event.pointerId !== pan.pointer || !viewport.value) return
  viewport.value.scrollTo(pan.left + pan.x - event.clientX, pan.top + pan.y - event.clientY)
}

onBeforeUnmount(closeLightbox)

function imageFailed(id: number) {
  failed.add(id)
  if (id === selected.value?.id) {
    mainLoading.value = false
    lightboxLoading.value = false
  }
}
</script>

<template>
  <section aria-labelledby="gallery-heading">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><h2 id="gallery-heading" class="h5 fw-bold mb-0">現場照片</h2><span class="small text-secondary">{{ images.length ? `${activeIndex + 1} / ${images.length} 張` : '0 張' }}</span></div>
    <p v-if="!selected" class="text-secondary py-4">這筆紀錄沒有現場照片。</p>
    <template v-else>
      <div class="main-photo rounded">
        <div v-if="failed.has(selected.id)" class="photo-message p-4 text-center" role="alert"><p class="mb-0">照片載入失敗，請稍後重新載入或切換其他照片。</p></div>
        <template v-else>
          <button ref="opener" type="button" class="open-photo" :disabled="mainLoading" aria-label="放大現場照片" @click="openLightbox"><img :key="selected.id" :src="selected.image_url" :alt="`現場照片 ${activeIndex + 1}`" @load="mainLoading = false" @error="imageFailed(selected.id)" /><span class="open-hint">⤢ 點擊放大</span></button>
          <div v-if="mainLoading" class="photo-message" role="status"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>照片載入中…</div>
        </template>
      </div>
      <div v-if="images.length > 1" class="gallery-thumbs mt-3" role="group" aria-label="切換現場照片">
        <button v-for="(image, index) in images" :key="image.id" type="button" class="photo-thumb" :class="{ selected: activeIndex === index }" :aria-label="`顯示第 ${index + 1} 張照片`" :aria-pressed="activeIndex === index" @click="activeIndex = index">
          <span v-if="failed.has(image.id)" class="small">載入失敗</span>
          <img v-else :src="image.image_url" alt="" loading="lazy" @error="imageFailed(image.id)" />
          <span class="thumb-number" aria-hidden="true">{{ index + 1 }}</span>
        </button>
      </div>
    </template>
    <Teleport to="body">
      <dialog ref="dialog" class="photo-lightbox" aria-label="現場照片放大檢視" aria-describedby="lightbox-help" @cancel.prevent="closeLightbox" @close="restorePage" @keydown="lightboxKeys">
        <template v-if="opened && selected">
          <div class="lightbox-toolbar">
            <span class="lightbox-count" aria-live="polite">{{ activeIndex + 1 }} / {{ images.length }}</span>
            <button type="button" :disabled="lightboxLoading || failed.has(selected.id)" :aria-pressed="zoomed" @click="toggleZoom">{{ zoomed ? '適合畫面' : '放大 2 倍' }}</button>
            <button data-close type="button" aria-label="關閉照片檢視" @click="closeLightbox">✕ 關閉</button>
          </div>
          <p id="lightbox-help" class="lightbox-help">放大後可拖曳或滑動查看。Esc 關閉，左右方向鍵切換照片。</p>
          <div ref="viewport" class="lightbox-viewport" :class="{ 'is-zoomed': zoomed }" tabindex="0" aria-label="照片，可捲動查看放大內容" @pointerdown="startPan" @pointermove="movePan" @pointerup="pan = null" @pointercancel="pan = null" @lostpointercapture="pan = null">
            <p v-if="failed.has(selected.id)" class="lightbox-error" role="alert">照片載入失敗，請關閉或切換其他照片。</p>
            <div v-else class="lightbox-canvas" :class="{ 'zoomed-canvas': zoomed }">
              <img :key="selected.id" :src="selected.image_url" :alt="`放大現場照片 ${activeIndex + 1}`" draggable="false" @load="lightboxLoading = false" @error="imageFailed(selected.id)" />
            </div>
          </div>
          <span v-if="lightboxLoading && !failed.has(selected.id)" class="lightbox-status" role="status">照片載入中…</span>
          <div v-if="images.length > 1" class="lightbox-navigation" aria-label="切換放大照片"><button type="button" @click="switchPhoto(-1)">← 上一張</button><button type="button" @click="switchPhoto(1)">下一張 →</button></div>
        </template>
      </dialog>
    </Teleport>
  </section>
</template>

<style scoped>
.main-photo { position: relative; display: grid; place-items: center; background: #eaf0f3; height: clamp(260px, 48vw, 520px); overflow: hidden; }
.open-photo { position: relative; width: 100%; height: 100%; min-height: 0; padding: 0; border: 0; background: transparent; cursor: zoom-in; }
.open-photo > img { width: 100%; height: 100%; object-fit: contain; min-height: 0; }
.open-hint { position: absolute; bottom: 12px; right: 12px; padding: 6px 10px; border-radius: 6px; color: white; background: #192f3acc; font-size: .8rem; }
.open-photo:focus-visible { outline: 3px solid #146b61; outline-offset: -4px; }
.photo-message { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: #536b77; background: #eaf0f3; }
.gallery-thumbs { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 8px; max-width: 480px; }
.photo-thumb { position: relative; aspect-ratio: 1; min-width: 0; min-height: 44px; border: 3px solid transparent; border-radius: 8px; padding: 2px; overflow: hidden; color: #7a3732; background: #eaf0f3; }
.photo-thumb > img { width: 100%; height: 100%; object-fit: cover; border-radius: 3px; display: block; }
.photo-thumb.selected { border-color: #146b61; }
.photo-thumb:focus-visible { outline: 3px solid #146b61; outline-offset: 2px; }
.thumb-number { position: absolute; right: 4px; bottom: 3px; padding: 0 4px; border-radius: 3px; font-size: .7rem; color: white; background: #192f3abf; }
.photo-lightbox { position: fixed; inset: 0; width: 100%; max-width: none; height: 100%; height: 100dvh; max-height: none; margin: 0; padding: max(12px, env(safe-area-inset-top)) max(12px, env(safe-area-inset-right)) max(12px, env(safe-area-inset-bottom)) max(12px, env(safe-area-inset-left)); border: 0; background: #0b141bf5; color: white; overflow: hidden; }
.photo-lightbox[open] { display: flex; flex-direction: column; gap: 12px; }
.photo-lightbox::backdrop { background: #0b141b; }
.lightbox-toolbar { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.lightbox-count { margin-right: auto; white-space: nowrap; }
.photo-lightbox button { min-width: 44px; min-height: 44px; border: 1px solid #78929c; border-radius: 8px; padding: 8px 12px; color: white; background: #263d48; }
.photo-lightbox button:disabled { opacity: .5; }
.photo-lightbox button:focus-visible, .lightbox-viewport:focus-visible { outline: 3px solid #73d4c6; outline-offset: -3px; }
.lightbox-help { font-size: .8rem; color: #c4d4dc; margin: 0; flex-shrink: 0; }
.lightbox-viewport { flex: 1; min-height: 0; overflow: auto; overscroll-behavior: contain; touch-action: pan-x pan-y; }
.lightbox-viewport.is-zoomed { cursor: grab; }
.lightbox-viewport.is-zoomed:active { cursor: grabbing; }
.lightbox-canvas { width: 100%; height: 100%; }
.lightbox-canvas.zoomed-canvas { width: 200%; height: 200%; }
.lightbox-canvas img { display: block; width: 100%; height: 100%; object-fit: contain; user-select: none; }
.lightbox-error { padding: 24px; text-align: center; }
.lightbox-status { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); pointer-events: none; }
.lightbox-navigation { display: flex; justify-content: center; gap: 16px; flex-shrink: 0; }
</style>
