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
}

export function readReportFilters(query: Record<string, unknown>): ReportFilters {
  return {
    site_name: typeof query.site_name === 'string' ? query.site_name : '',
    issue_type: typeof query.issue_type === 'string' ? query.issue_type : '',
    severity: typeof query.severity === 'string' ? query.severity : '',
  }
}

export function reportFilterQuery(filters: ReportFilters): Record<string, string> {
  return Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''))
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
