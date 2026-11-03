import { ref } from 'vue'
import { isAxiosError } from 'axios'

export function useReportDeletion(send: (password: string, signal: AbortSignal) => Promise<unknown>) {
  const password = ref('')
  const error = ref('')
  const pending = ref(false)
  let controller: AbortController | null = null

  async function submit(): Promise<boolean> {
    if (pending.value) return false
    error.value = ''
    if (!password.value) {
      error.value = '請輸入管理密碼。'
      return false
    }
    pending.value = true
    const entered = password.value
    password.value = ''
    const request = new AbortController()
    controller = request
    try {
      await send(entered, request.signal)
      return !request.signal.aborted
    } catch (exception) {
      if (request.signal.aborted) return false
      if (isAxiosError(exception)) {
        const status = exception.response?.status
        if (status === 403) error.value = '管理密碼錯誤，請重新輸入。'
        else if (status === 422) error.value = '請輸入有效的管理密碼。'
        else if (status === 404) error.value = '找不到巡檢紀錄，可能已被刪除。請重新載入確認。'
        else if (status === 429) error.value = '刪除嘗試過於頻繁，請一分鐘後再試。'
        else if (status === 503) error.value = '管理密碼尚未正確設定，刪除功能暫停使用。'
        else if (status === 409) {
          const message = exception.response?.data?.message
          error.value = message === '照片儲存忙碌中，請稍後再試。' ? message : '這筆紀錄正在刪除，請稍後重新載入確認。'
        }
        else if (!exception.response) error.value = '網路連線失敗，刪除結果尚未確認。請先重新載入確認，勿直接重複送出。'
        else error.value = typeof exception.response.data?.message === 'string' ? exception.response.data.message : '刪除失敗，請重新載入確認紀錄狀態。'
      } else error.value = '刪除失敗，請重新載入確認紀錄狀態。'
      return false
    } finally {
      password.value = ''
      pending.value = false
      controller = null
    }
  }

  function cancel() {
    controller?.abort()
    password.value = ''
    error.value = ''
  }

  return { password, error, pending, submit, cancel }
}
