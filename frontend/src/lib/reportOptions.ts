export const sites = ['七期住宅 A 棟', '南屯辦公室 3 樓', '逢甲社區公共區']
export const issueTypes = ['系統櫃安裝', '水電', '裝修', '安全防護', '其他']
export const severities = [
  { value: 'low', label: '低' },
  { value: 'medium', label: '中' },
  { value: 'high', label: '高' },
]

export const reportStatuses = [
  { value: 'pending', label: '待處理', badgeClass: 'text-bg-secondary' },
  { value: 'in_progress', label: '處理中', badgeClass: 'text-bg-primary' },
  { value: 'completed', label: '已完成', badgeClass: 'text-bg-success' },
  { value: 'reviewed', label: '已審核', badgeClass: 'text-bg-dark' },
] as const

export type ReportStatus = (typeof reportStatuses)[number]['value']
