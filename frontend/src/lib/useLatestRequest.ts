import { ref, shallowRef } from 'vue'
import { isAxiosError } from 'axios'

// Aborting saves work; the version check also protects against transports that ignore abort.
export function useLatestRequest<T>() {
  const data = shallowRef<T | null>(null)
  const loading = ref(false)
  const error = ref('')
  const notFound = ref(false)
  let version = 0
  let controller: AbortController | null = null

  async function run(fetcher: (signal: AbortSignal) => Promise<T>) {
    const current = ++version
    controller?.abort()
    controller = new AbortController()
    const signal = controller.signal
    loading.value = true
    data.value = null
    error.value = ''
    notFound.value = false
    try {
      const result = await fetcher(signal)
      if (version === current) data.value = result
    } catch (exception) {
      if (version !== current || signal.aborted) return
      if (isAxiosError(exception) && exception.response?.status === 404) {
        notFound.value = true
      } else {
        error.value = '暫時無法載入資料，請確認網路連線後重新載入。'
      }
    } finally {
      if (version === current) loading.value = false
    }
  }

  function cancel() {
    version++
    controller?.abort()
    loading.value = false
  }

  return { data, loading, error, notFound, run, cancel }
}
