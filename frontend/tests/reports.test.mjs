import assert from 'node:assert/strict'
import test from 'node:test'
import { formatReportDate, normalizeReportKeyword, readReportFilters, reportFilterQuery, reportKeywordError } from '../src/lib/reports.ts'
import { sites, issueTypes, severities } from '../src/lib/reportOptions.ts'

test('filters preserve exact API values and omit all-options without forwarding unrelated query parameters', () => {
  const filters = readReportFilters({ site_name: '七期住宅 A 棟', issue_type: '水電', severity: 'high', returnTo: 'https://example.invalid/' })
  assert.deepEqual(reportFilterQuery(filters), { site_name: '七期住宅 A 棟', issue_type: '水電', severity: 'high' })
  assert.deepEqual(reportFilterQuery(readReportFilters({})), {})
  assert.deepEqual(readReportFilters({ site_name: ['a', 'b'], severity: null, status: ['pending'], keyword: ['a', 'b'] }), { site_name: '', issue_type: '', severity: '', status: '', keyword: '' })
})

test('URL filter round-trip retains the combined criteria for detail and back navigation', () => {
  const original = { site_name: sites[1], issue_type: '水電', severity: 'medium', status: '', keyword: '' }
  const query = new URLSearchParams(reportFilterQuery(original))
  assert.deepEqual(readReportFilters(Object.fromEntries(query)), original)
})

test('keyword normalization matches Laravel edge whitespace and preserves internal whitespace and zero', () => {
  const whitespace = '\0\t\n\r\v\f \u0085\u00a0\u00ad\u034f\u061c\u115f\u1160\u1680\u17b4\u17b5\u180e\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200a\u200b\u200c\u200d\u200e\u200f\u2028\u2029\u202f\u205f\u2060\u2061\u2062\u2063\u2064\u2065\u206a\u206b\u206c\u206d\u206e\u206f\u2800\u3000\u3164\ufeff\uffa0\u{1d159}\u{1d173}\u{1d174}\u{1d175}\u{1d176}\u{1d177}\u{1d178}\u{1d179}\u{1d17a}\u{e0020}'
  for (const character of whitespace) assert.equal(normalizeReportKeyword(`${character}漏  水${character}`), '漏  水')
  assert.equal(normalizeReportKeyword(whitespace), '')
  assert.equal(normalizeReportKeyword(' 0 '), '0')
  assert.equal(normalizeReportKeyword('a\u200db'), 'a\u200db')
  assert.equal(normalizeReportKeyword('\u2066a\u2069'), '\u2066a\u2069')
  assert.deepEqual(reportFilterQuery(readReportFilters({ keyword: whitespace })), {})
})

test('keyword limits count Unicode code points after trimming without truncation', () => {
  assert.equal(reportKeywordError(` \u200b${'😀'.repeat(100)}\u{e0020}`), '')
  assert.match(reportKeywordError('😀'.repeat(101)), /100/)
  assert.equal(readReportFilters({ keyword: '😀'.repeat(101) }).keyword.length, 202)
})

test('long interior whitespace is preserved and rejected by length without truncation', () => {
  const interior = `😀${' '.repeat(100000)}b`
  assert.equal(normalizeReportKeyword(`\u{e0020}${interior}\u{1d173}`), interior)
  assert.match(reportKeywordError(interior), /100/)
  assert.equal(readReportFilters({ keyword: interior }).keyword, interior)
  assert.equal(normalizeReportKeyword(' '.repeat(100000)), '')
})

test('keyword URL round-trip preserves literal punctuation, Unicode and internal spaces', () => {
  for (const keyword of ['!%_', "\\' OR 1=1 --", 'a+b&c#d=e?', '漏  水😀', '0']) {
    const filters = readReportFilters({ keyword })
    const query = new URLSearchParams(reportFilterQuery(filters))
    assert.equal(readReportFilters(Object.fromEntries(query)).keyword, keyword)
  }
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
