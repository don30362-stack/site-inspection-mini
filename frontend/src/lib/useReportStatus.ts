import { ref } from 'vue'
import { isAxiosError } from 'axios'
import type { ReportStatus } from './reportOptions.ts'
import type { Report } from './reports.ts'

export function useReportStatus(
  initial: ReportStatus,
  send: (status: ReportStatus, signal: AbortSignal) => Promise<Report>,
) {
  const selected = ref(initial)
  const current = ref(initial)
  const pending = ref(false)
  const error = ref('')
  const success = ref('')
  let controller: AbortController | null = null

  async function save(): Promise<Report | null> {
    if (pending.value || selected.value === current.value) return null
    const status = selected.value
    const request = new AbortController()
    controller = request
    pending.value = true
    error.value = ''
    success.value = ''
    try {
      const report = await send(status, request.signal)
      if (request.signal.aborted) return null
      current.value = report.status
      selected.value = report.status
      success.value = '處理狀態已更新。'
      return report
    } catch (exception) {
      if (request.signal.aborted) return null
      selected.value = current.value
      const code = isAxiosError(exception) ? exception.response?.status : undefined
      error.value = code === 429 ? '狀態修改過於頻繁，請一分鐘後再試。'
        : code === 404 ? '找不到巡檢紀錄，請返回列表確認。'
        : code === 422 ? '處理狀態無效，請重新選擇。'
        : '無法確認狀態是否更新，請重新載入紀錄後再試。'
      return null
    } finally {
      if (!request.signal.aborted) pending.value = false
    }
  }

  function cancel() {
    controller?.abort()
    pending.value = false
  }

  return { selected, current, pending, error, success, save, cancel }
}
