import type { ReportStatus } from './reportOptions'

export type Severity = 'low' | 'medium' | 'high'

export interface ReportImage {
  id: number
  image_url: string
  sort_order: number
}

export interface Report {
  id: number
  site_name: string
  issue_type: string
  severity: Severity
  status: ReportStatus
  description: string
  images: ReportImage[]
  created_at: string | null
  updated_at: string | null
}

export interface ApiResponse<T> {
  data: T
  message: string
  errors: Record<string, string[]>
}

export interface ReportFilters {
  site_name: string
  issue_type: string
  severity: string
  status: string
  keyword: string
}

export const reportKeywordLimit = 100

// Match Laravel Str::trim, including its invisible characters and PCRE whitespace.
const keywordWhitespace = /^[\u0000\u0009-\u000d\u0020\u0085\u00a0\u00ad\u034f\u061c\u115f\u1160\u1680\u17b4\u17b5\u180e\u2000-\u200f\u2028\u2029\u202f\u205f\u2060-\u2065\u206a-\u206f\u2800\u3000\u3164\ufeff\uffa0\u{1d159}\u{1d173}-\u{1d17a}\u{e0020}]$/u

export function normalizeReportKeyword(value: string): string {
  const characters = Array.from(value)
  let start = 0
  let end = characters.length
  // Scan each edge once; a long whitespace run inside the keyword stays untouched.
  while (start < end && keywordWhitespace.test(characters[start]!)) start++
  while (end > start && keywordWhitespace.test(characters[end - 1]!)) end--
  return characters.slice(start, end).join('')
}

export function reportKeywordError(value: string): string {
  return Array.from(normalizeReportKeyword(value)).length > reportKeywordLimit
    ? `描述關鍵字最多 ${reportKeywordLimit} 個字元。` : ''
}

export interface PaginationMeta {
  current_page: number
  per_page: number
  last_page: number
  total: number
  from: number | null
  to: number | null
}

export interface ReportListResponse extends ApiResponse<Report[]> {
  meta: PaginationMeta
}

export function readReportPage(query: Record<string, unknown>): number {
  const value = query.page
  if (typeof value !== 'string' || !/^[1-9]\d*$/.test(value)) return 1
  const page = Number(value)
  return Number.isSafeInteger(page) ? page : 1
}

export function reportListQuery(filters: ReportFilters, page = 1): Record<string, string> {
  return { ...reportFilterQuery(filters), ...(page > 1 ? { page: String(page) } : {}) }
}

// At most three page numbers keep the controls usable on narrow phones.
export function reportPageNumbers(current: number, last: number): number[] {
  const start = Math.max(1, Math.min(current - 1, last - 2))
  return Array.from({ length: Math.min(3, last) }, (_, index) => start + index)
}

export function readReportFilters(query: Record<string, unknown>): ReportFilters {
  return {
    site_name: typeof query.site_name === 'string' ? query.site_name : '',
    issue_type: typeof query.issue_type === 'string' ? query.issue_type : '',
    severity: typeof query.severity === 'string' ? query.severity : '',
    status: typeof query.status === 'string' ? query.status : '',
    keyword: typeof query.keyword === 'string' ? normalizeReportKeyword(query.keyword) : '',
  }
}

export function reportFilterQuery(filters: ReportFilters): Record<string, string> {
  return Object.fromEntries(Object.entries({ ...filters, keyword: normalizeReportKeyword(filters.keyword) }).filter(([, value]) => value !== ''))
}

const taipeiDate = new Intl.DateTimeFormat('zh-TW', {
  timeZone: 'Asia/Taipei', year: 'numeric', month: '2-digit', day: '2-digit',
  hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
})

export function formatReportDate(value: string | null): string {
  if (!value) return '未提供時間'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '時間格式不明' : taipeiDate.format(date)
}
