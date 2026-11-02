import { computed, reactive, ref } from 'vue'
import { preparePhoto } from './photo.ts'

export const MAX_PHOTOS = 5

interface PhotoItem {
  key: number
  file: File | null
  url: string
  status: 'queued' | 'processing' | 'ready'
  converted: boolean
  error: string
}

export function usePhotoCollection(prepare = preparePhoto, urls = URL) {
  const items = reactive<PhotoItem[]>([])
  const photoError = ref('')
  const processingPhoto = computed(() => items.some((item) => item.status !== 'ready'))
  let nextKey = 0
  let version = 0

  function clearPhotos() {
    version++
    for (const item of items) if (item.url) urls.revokeObjectURL(item.url)
    items.splice(0)
    photoError.value = ''
  }

  async function addPhotos(files: File[]) {
    if (processingPhoto.value || !files.length) return
    if (items.length + files.length > MAX_PHOTOS) {
      photoError.value = `最多可新增 5 張照片，目前已有 ${items.length} 張；本次選取未加入，請重新選取。`
      return
    }
    photoError.value = ''
    const currentVersion = version
    const pending = files.map((source) => {
      const item = reactive<PhotoItem>({ key: nextKey++, file: null, url: '', status: 'queued', converted: false, error: '' })
      items.push(item)
      return { source, item }
    })
    // Decode sequentially, including mixed selections, to bound mobile memory use.
    for (const { source, item } of pending) {
      if (currentVersion !== version) return
      item.status = 'processing'
      try {
        const result = await prepare(source)
        if (currentVersion !== version) return
        item.url = urls.createObjectURL(result.file)
        item.file = result.file
        item.converted = result.converted
        item.status = 'ready'
      } catch (error) {
        if (currentVersion !== version) return
        items.splice(items.findIndex((entry) => entry.key === item.key), 1)
        const message = error instanceof Error ? error.message : '照片處理失敗。'
        photoError.value += `${source.name}：${message} `
      }
    }
  }

  async function replacePhoto(key: number, source?: File) {
    if (!source || processingPhoto.value) return
    const item = items.find((entry) => entry.key === key)
    if (!item) return
    photoError.value = ''
    item.error = ''
    item.status = 'processing'
    const currentVersion = version
    try {
      const result = await prepare(source)
      if (currentVersion !== version) return
      const url = urls.createObjectURL(result.file)
      const previousUrl = item.url
      item.file = result.file
      item.url = url
      item.converted = result.converted
      urls.revokeObjectURL(previousUrl)
    } catch (error) {
      if (currentVersion !== version) return
      item.error = `更換失敗，已保留原照片。${error instanceof Error ? error.message : '請重新選取照片。'}`
    } finally {
      if (currentVersion === version) item.status = 'ready'
    }
  }

  function removePhoto(key: number) {
    if (processingPhoto.value) return
    const index = items.findIndex((item) => item.key === key)
    if (index < 0) return
    const item = items[index]!
    if (item.url) urls.revokeObjectURL(item.url)
    items.splice(index, 1)
    photoError.value = ''
  }

  // Move the existing object: no decoding, File copying or preview URL changes.
  function movePhoto(key: number, to: number) {
    if (processingPhoto.value || !Number.isInteger(to) || to < 0 || to >= items.length) return false
    const from = items.findIndex((item) => item.key === key)
    if (from < 0 || from === to) return false
    items.splice(to, 0, items.splice(from, 1)[0]!)
    return true
  }

  return { items, photoError, processingPhoto, addPhotos, replacePhoto, removePhoto, clearPhotos, movePhoto }
}
