<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import type { ReportImage } from '../lib/reports'

const props = defineProps<{ images: ReportImage[] }>()
const activeIndex = ref(0)
const selected = computed(() => props.images[activeIndex.value])
const failed = reactive(new Set<number>())
const mainLoading = ref(true)
watch(() => selected.value?.id, () => { mainLoading.value = true })

function imageFailed(id: number) {
  failed.add(id)
  if (id === selected.value?.id) mainLoading.value = false
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
          <img :key="selected.id" :src="selected.image_url" :alt="`現場照片 ${activeIndex + 1}`" @load="mainLoading = false" @error="imageFailed(selected.id)" />
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
  </section>
</template>

<style scoped>
.main-photo { position: relative; display: grid; place-items: center; background: #eaf0f3; height: clamp(260px, 48vw, 520px); overflow: hidden; }
.main-photo > img { width: 100%; height: 100%; object-fit: contain; min-height: 0; }
.photo-message { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: #536b77; background: #eaf0f3; }
.gallery-thumbs { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 8px; max-width: 480px; }
.photo-thumb { position: relative; aspect-ratio: 1; min-width: 0; min-height: 44px; border: 3px solid transparent; border-radius: 8px; padding: 2px; overflow: hidden; color: #7a3732; background: #eaf0f3; }
.photo-thumb > img { width: 100%; height: 100%; object-fit: cover; border-radius: 3px; display: block; }
.photo-thumb.selected { border-color: #146b61; }
.photo-thumb:focus-visible { outline: 3px solid #146b61; outline-offset: 2px; }
.thumb-number { position: absolute; right: 4px; bottom: 3px; padding: 0 4px; border-radius: 3px; font-size: .7rem; color: white; background: #192f3abf; }
</style>
