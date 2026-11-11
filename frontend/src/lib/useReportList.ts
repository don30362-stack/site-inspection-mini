import { computed, onScopeDispose, ref, watch } from 'vue'
import type { RouteLocationNormalizedLoaded, Router } from 'vue-router'
import { normalizeReportKeyword, readReportFilters, readReportPage, reportKeywordError, reportListQuery, reportPageNumbers } from './reports.ts'
import type { ReportFilters, ReportListResponse } from './reports.ts'
import { useLatestRequest } from './useLatestRequest.ts'

export function useReportList(
  route: RouteLocationNormalizedLoaded,
  router: Router,
  fetchReports: (params: Record<string, string>, signal: AbortSignal) => Promise<ReportListResponse>,
) {
  const filters = computed(() => readReportFilters(route.query))
  const page = computed(() => readReportPage(route.query))
  const query = computed(() => reportListQuery(filters.value, page.value))
  const request = useLatestRequest<ReportListResponse>()
  const reports = computed(() => request.data.value?.data)
  const meta = computed(() => request.data.value?.meta)
  const pages = computed(() => meta.value ? reportPageNumbers(meta.value.current_page, meta.value.last_page) : [])
  const hasFilters = computed(() => Object.values(filters.value).some(Boolean))
  const keywordDraft = ref('')
  const keywordComposing = ref(false)
  const keywordQueryError = computed(() => reportKeywordError(filters.value.keyword))
  const keywordError = computed(() => reportKeywordError(keywordDraft.value) || keywordQueryError.value)

  function loadReports() {
    if (keywordQueryError.value) {
      request.cancel()
      request.data.value = null
      return
    }
    const params = query.value
    return request.run(signal => fetchReports(params, signal))
  }

  function setFilter(field: keyof ReportFilters, value: string) {
    return router.replace({ path: '/reports', query: reportListQuery({ ...filters.value, [field]: value }) })
  }

  function clearFilters() {
    keywordDraft.value = ''
    return router.replace({ path: '/reports', query: {} })
  }

  function submitKeyword() {
    if (keywordComposing.value) return
    keywordDraft.value = normalizeReportKeyword(keywordDraft.value)
    if (reportKeywordError(keywordDraft.value)) return
    return setFilter('keyword', keywordDraft.value)
  }

  function setPage(value: number) {
    return router.push({ path: '/reports', query: reportListQuery(filters.value, value) })
  }

  function detailLink(id: number) {
    return { path: `/reports/${id}`, query: query.value }
  }

  watch(() => route.query, () => {
    request.cancel()
    keywordDraft.value = filters.value.keyword
    // Normalize before fetching; a malformed URL never reaches the API.
    if ((route.query.page !== undefined && route.query.page !== query.value.page)
      || (route.query.keyword !== undefined && route.query.keyword !== query.value.keyword)) {
      void router.replace({ path: '/reports', query: query.value })
      return
    }
    void loadReports()
  }, { immediate: true, flush: 'sync' })

  // Only accepted responses enter data, so stale requests cannot redirect the URL.
  watch(request.data, result => {
    if (result && page.value > result.meta.last_page) {
      void router.replace({ path: '/reports', query: reportListQuery(filters.value, result.meta.last_page) })
    }
  }, { flush: 'sync' })
  onScopeDispose(request.cancel)

  return { reports, meta, pages, filters, hasFilters, loadReports, setFilter, clearFilters, setPage, detailLink,
    keywordDraft, keywordComposing, keywordError, keywordQueryError, submitKeyword,
    loading: request.loading, error: request.error, notFound: request.notFound }
}
