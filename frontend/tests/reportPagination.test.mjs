import assert from 'node:assert/strict'
import test from 'node:test'
import { effectScope } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { readReportFilters, readReportPage, reportListQuery, reportPageNumbers } from '../src/lib/reports.ts'
import { useReportList } from '../src/lib/useReportList.ts'

const settle = async () => {
  for (let i = 0; i < 5; i++) await new Promise(resolve => setImmediate(resolve))
}
const response = (page = 1, total = 41) => ({
  data: total && page <= Math.ceil(total / 20) ? [{ id: page }] : [],
  meta: { current_page: page, per_page: 20, last_page: Math.max(1, Math.ceil(total / 20)), total,
    from: total ? (page - 1) * 20 + 1 : null, to: total ? Math.min(page * 20, total) : null },
  message: '查詢成功。', errors: {},
})
function deferred() {
  let resolve, reject
  const promise = new Promise((done, fail) => { resolve = done; reject = fail })
  return { promise, resolve, reject }
}
async function setup(t, url = '/reports', fetcher = params => response(Number(params.page || 1))) {
  const router = createRouter({ history: createMemoryHistory(), routes: [
    { path: '/reports', component: {} }, { path: '/reports/:id', component: {} },
  ] })
  await router.push(url)
  const calls = []
  const scope = effectScope()
  const list = scope.run(() => useReportList({ get query() { return router.currentRoute.value.query } }, router,
    async (params, signal) => { calls.push({ params, signal }); return fetcher(params, signal) }))
  t.after(() => scope.stop())
  await settle()
  return { list, router, calls, scope }
}

test('page parsing rejects malformed, repeated and unsafe URL values', () => {
  for (const page of [undefined, null, '', '0', '-1', '1.5', 'abc', '1e2', ' 2', '+2', ['2'], '9007199254740992']) {
    assert.equal(readReportPage({ page }), 1)
  }
  assert.equal(readReportPage({ page: '2' }), 2)
  assert.deepEqual(reportListQuery(readReportFilters({}), 1), {})
})

test('page controls stay bounded at the start, middle and end of large results', () => {
  assert.deepEqual(reportPageNumbers(1, 1), [1])
  assert.deepEqual(reportPageNumbers(1, 100), [1, 2, 3])
  assert.deepEqual(reportPageNumbers(50, 100), [49, 50, 51])
  assert.deepEqual(reportPageNumbers(100, 100), [98, 99, 100])
})

test('URL initialization, pagination, history and filter changes preserve the correct criteria', async t => {
  const { list, router, calls } = await setup(t, '/reports?page=2&site_name=A&issue_type=水電&severity=high&status=in_progress&keyword=漏水')
  assert.deepEqual(calls.at(-1).params, { page: '2', site_name: 'A', issue_type: '水電', severity: 'high', status: 'in_progress', keyword: '漏水' })
  assert.equal(list.meta.value.current_page, 2)
  await list.setPage(3)
  await settle()
  assert.equal(router.currentRoute.value.query.page, '3')
  assert.equal(calls.at(-1).params.severity, 'high')
  assert.equal(calls.at(-1).params.status, 'in_progress')
  assert.equal(calls.at(-1).params.keyword, '漏水')
  router.back()
  await settle()
  assert.equal(list.meta.value.current_page, 2)
  router.forward()
  await settle()
  assert.equal(list.meta.value.current_page, 3)
  for (const [field, value] of [['site_name', 'B'], ['issue_type', '裝修'], ['severity', 'low'], ['status', 'reviewed']]) {
    await list.setPage(2)
    await list.setFilter(field, value)
    await settle()
    assert.equal(router.currentRoute.value.query.page, undefined)
    assert.equal(list.meta.value.current_page, 1)
    assert.equal(calls.at(-1).params[field], value)
    assert.equal(calls.at(-1).params.keyword, '漏水')
  }
  await list.setPage(2)
  await list.clearFilters()
  await settle()
  assert.deepEqual(router.currentRoute.value.query, {})
  assert.deepEqual(calls.at(-1).params, {})
})

test('detail and deletion return target retain page and filters, including after URL reload', async t => {
  const { list, router } = await setup(t, '/reports?page=2&site_name=A&severity=high&status=completed&keyword=漏水')
  await router.push(list.detailLink(42))
  const query = router.currentRoute.value.query
  // Same query builder used by ReportDetailView for its back link and deleted event.
  const back = { path: '/reports', query: reportListQuery(readReportFilters(query), readReportPage(query)) }
  assert.deepEqual(back.query, { page: '2', site_name: 'A', severity: 'high', status: 'completed', keyword: '漏水' })
  await router.push(back)
  await settle()
  assert.equal(list.meta.value.current_page, 2)
  const reloaded = await setup(t, router.currentRoute.value.fullPath)
  assert.equal(reloaded.list.meta.value.current_page, 2)
  assert.equal(reloaded.list.filters.value.site_name, 'A')
  assert.equal(reloaded.list.filters.value.status, 'completed')
  assert.equal(reloaded.list.keywordDraft.value, '漏水')
})

test('search is explicit, composition safe, normalized, and replaces history while retaining four criteria', async t => {
  const { list, router, calls } = await setup(t, '/reports?site_name=A&issue_type=水電&severity=high&status=pending')
  await list.setPage(2)
  await settle()
  const count = calls.length
  list.keywordDraft.value = ' \u200b漏  水\u{e0020}'
  await settle()
  assert.equal(calls.length, count)
  list.keywordComposing.value = true
  await list.submitKeyword()
  assert.equal(calls.length, count)
  list.keywordComposing.value = false
  await list.submitKeyword()
  await settle()
  assert.deepEqual(calls.at(-1).params, { site_name: 'A', issue_type: '水電', severity: 'high', status: 'pending', keyword: '漏  水' })
  assert.equal(list.keywordDraft.value, '漏  水')
  list.keywordDraft.value = ' 漏  水 '
  await list.submitKeyword()
  await settle()
  assert.equal(list.keywordDraft.value, '漏  水')
  assert.equal(calls.length, count + 1)
  await list.setPage(2)
  await settle()
  list.keywordDraft.value = '未提交草稿'
  router.back()
  await settle()
  assert.equal(list.keywordDraft.value, '漏  水')
  assert.equal(router.currentRoute.value.query.page, undefined)
  router.back()
  await settle()
  assert.equal(list.keywordDraft.value, '')
  router.forward()
  await settle()
  assert.equal(list.keywordDraft.value, '漏  水')
  await list.clearFilters()
  await settle()
  list.keywordDraft.value = '未提交'
  await list.clearFilters()
  assert.equal(list.keywordDraft.value, '')
})

test('keyword URL canonicalization fetches once and removes empty, bare or repeated values', async t => {
  for (const [query, keyword] of [['keyword=%20漏水%20', '漏水'], ['keyword=%200%20', '0'], ['keyword=%20%20', undefined], ['keyword', undefined], ['keyword=a&keyword=b', undefined]]) {
    const { router, calls } = await setup(t, `/reports?page=2&site_name=A&${query}`)
    assert.equal(router.currentRoute.value.query.keyword, keyword)
    assert.equal(calls.length, 1)
    assert.equal(calls[0].params.keyword, keyword)
    assert.equal(calls[0].params.page, '2')
    assert.equal(calls[0].params.site_name, 'A')
  }
})

test('oversized direct URL and reload never reach API, and shorter submission recovers', async t => {
  const oversized = '😀'.repeat(101)
  const { list, router, calls } = await setup(t, `/reports?keyword=${oversized}&severity=high`)
  assert.equal(calls.length, 0)
  assert.match(list.keywordError.value, /100/)
  assert.equal(list.keywordDraft.value, oversized)
  await list.loadReports()
  assert.equal(calls.length, 0)
  list.keywordDraft.value = '😀'.repeat(100)
  await list.submitKeyword()
  await settle()
  assert.equal(calls.length, 1)
  assert.equal(calls[0].params.keyword, '😀'.repeat(100))
  assert.equal(calls[0].params.severity, 'high')
  assert.equal(list.keywordError.value, '')
  list.keywordDraft.value = oversized
  await list.submitKeyword()
  assert.equal(calls.length, 1)
  assert.equal(router.currentRoute.value.query.keyword, '😀'.repeat(100))
})

test('new search cancels old results and invalid URL cancels in-flight requests', async t => {
  const pending = []
  const { list, router, calls } = await setup(t, '/reports?keyword=old&page=3', () => {
    const task = deferred(); pending.push(task); return task.promise
  })
  list.keywordDraft.value = 'new'
  await list.submitKeyword()
  pending[1].resolve(response(1))
  await settle()
  pending[0].resolve(response(3, 0))
  await settle()
  assert.equal(calls[0].signal.aborted, true)
  assert.equal(router.currentRoute.value.query.keyword, 'new')
  assert.equal(list.meta.value.total, 41)
  await list.setPage(2)
  await router.push({ path: '/reports', query: { keyword: 'x'.repeat(101) } })
  await settle()
  assert.equal(calls.at(-1).signal.aborted, true)
  pending[2].resolve(response(2))
  await settle()
  assert.equal(list.reports.value, undefined)
  assert.equal(list.meta.value, undefined)
  assert.equal(list.loading.value, false)
})

test('invalid URL pages normalize before fetching without loops', async t => {
  for (const query of ['page=0', 'page=-2', 'page=1.5', 'page=abc', 'page=', 'page=2&page=3', 'page=9007199254740992']) {
    const { router, calls } = await setup(t, `/reports?${query}&site_name=A`)
    assert.deepEqual(router.currentRoute.value.query, { site_name: 'A' })
    assert.equal(calls.length, 1)
    assert.deepEqual(calls[0].params, { site_name: 'A' })
  }
})

test('out of range results redirect to the last valid page; zero results settle on page one', async t => {
  for (const total of [21, 0]) {
    const { list, router, calls } = await setup(t, '/reports?page=8&severity=high&keyword=!%25_', params => response(Number(params.page || 1), total))
    assert.equal(router.currentRoute.value.query.page, total ? '2' : undefined)
    assert.equal(list.meta.value.total, total)
    assert.deepEqual(list.reports.value, total ? [{ id: 2 }] : [])
    assert.equal(calls.length, 2)
    assert.equal(calls[1].params.severity, 'high')
    assert.equal(calls[1].params.keyword, '!%_')
    assert.equal(router.currentRoute.value.query.keyword, '!%_')
  }
})

test('reload after deleting the last record on a page corrects the URL', async t => {
  let total = 21
  const { list, router } = await setup(t, '/reports?page=2', params => response(Number(params.page || 1), total))
  total = 20
  await list.loadReports()
  await settle()
  assert.equal(router.currentRoute.value.query.page, undefined)
  assert.equal(list.meta.value.current_page, 1)
  assert.equal(list.meta.value.total, 20)
})

test('rapid page changes ignore stale rows, metadata, redirects and failures even when abort is ignored', async t => {
  const pending = []
  const { list, router, calls } = await setup(t, '/reports', () => {
    const task = deferred(); pending.push(task); return task.promise
  })
  await list.setPage(2)
  await list.setPage(3)
  assert.equal(calls[0].signal.aborted, true)
  assert.equal(calls[1].signal.aborted, true)
  pending[2].resolve(response(3))
  await settle()
  pending[0].resolve(response(1, 0))
  pending[1].reject(new Error('stale failure'))
  await settle()
  assert.equal(router.currentRoute.value.query.page, '3')
  assert.deepEqual(list.reports.value, [{ id: 3 }])
  assert.equal(list.meta.value.current_page, 3)
  assert.equal(list.error.value, '')
  assert.equal(list.loading.value, false)
})

test('errors can be retried and leaving the list cancels pending pagination', async t => {
  let fail = true
  let pending
  const { list, scope, calls } = await setup(t, '/reports?page=2', () => {
    if (pending) return pending.promise
    if (fail) throw new Error('offline')
    return response(2)
  })
  assert.match(list.error.value, /重新載入/)
  fail = false
  await list.loadReports()
  assert.equal(list.error.value, '')
  assert.equal(list.meta.value.current_page, 2)
  pending = deferred()
  const task = list.loadReports()
  scope.stop()
  assert.equal(calls.at(-1).signal.aborted, true)
  pending.resolve(response(2))
  await task
  assert.equal(list.reports.value, undefined)
  assert.equal(list.loading.value, false)
})
