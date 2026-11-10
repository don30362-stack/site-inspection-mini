import assert from 'node:assert/strict'
import test from 'node:test'
import { formatReportDate, readReportFilters, reportFilterQuery } from '../src/lib/reports.ts'
import { sites, issueTypes, severities } from '../src/lib/reportOptions.ts'

test('filters preserve exact API values and omit all-options without forwarding unrelated query parameters', () => {
  const filters = readReportFilters({ site_name: '七期住宅 A 棟', issue_type: '水電', severity: 'high', returnTo: 'https://example.invalid/' })
  assert.deepEqual(reportFilterQuery(filters), { site_name: '七期住宅 A 棟', issue_type: '水電', severity: 'high' })
  assert.deepEqual(reportFilterQuery(readReportFilters({})), {})
  assert.deepEqual(readReportFilters({ site_name: ['a', 'b'], severity: null, status: ['pending'] }), { site_name: '', issue_type: '', severity: '', status: '' })
})

test('URL filter round-trip retains the combined criteria for detail and back navigation', () => {
  const original = { site_name: sites[1], issue_type: '水電', severity: 'medium', status: '' }
  const query = new URLSearchParams(reportFilterQuery(original))
  assert.deepEqual(readReportFilters(Object.fromEntries(query)), original)
})

test('dates use Asia/Taipei including a UTC date crossing midnight', () => {
  const display = formatReportDate('2026-11-02T16:30:00.000000Z')
  assert.match(display, /2026\/11\/03/)
  assert.match(display, /00:30/)
  assert.equal(formatReportDate(null), '未提供時間')
  assert.equal(formatReportDate('not a date'), '時間格式不明')
})

test('shared options retain the existing work sites and backend issue/severity values', () => {
  assert.deepEqual(sites, ['七期住宅 A 棟', '南屯辦公室 3 樓', '逢甲社區公共區'])
  assert.deepEqual(issueTypes, ['系統櫃安裝', '水電', '裝修', '安全防護', '其他'])
  assert.deepEqual(severities.map(({ value, label }) => [value, label]), [['low', '低'], ['medium', '中'], ['high', '高']])
})
