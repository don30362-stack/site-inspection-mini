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
  const { list, router, calls } = await setup(t, '/reports?page=2&site_name=A&issue_type=水電&severity=high&status=in_progress')
  assert.deepEqual(calls.at(-1).params, { page: '2', site_name: 'A', issue_type: '水電', severity: 'high', status: 'in_progress' })
  assert.equal(list.meta.value.current_page, 2)
  await list.setPage(3)
  await settle()
  assert.equal(router.currentRoute.value.query.page, '3')
  assert.equal(calls.at(-1).params.severity, 'high')
  assert.equal(calls.at(-1).params.status, 'in_progress')
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
  }
  await list.setPage(2)
  await list.clearFilters()
  await settle()
  assert.deepEqual(router.currentRoute.value.query, {})
  assert.deepEqual(calls.at(-1).params, {})
})

test('first and last page jumps preserve all filters and browser history with filtered results', async t => {
  const filters = { site_name: 'A', issue_type: '水電', severity: 'high', status: 'in_progress' }
  const matching = Array.from({ length: 101 }, (_, index) => ({ id: index + 1, ...filters }))
  // Each extra row differs in exactly one criterion, so dropping any filter changes the total.
  const excluded = Object.entries({ site_name: 'B', issue_type: '裝修', severity: 'low', status: 'reviewed' })
    .map(([field, value], index) => ({ id: 102 + index, ...filters, [field]: value }))
  const records = [...matching, ...excluded].sort((left, right) => right.id - left.id)
  const fetcher = params => {
    const filtered = records.filter(record => Object.keys(filters)
      .every(field => !params[field] || record[field] === params[field]))
    const page = Number(params.page || 1)
    return { ...response(page, filtered.length), data: filtered.slice((page - 1) * 20, page * 20) }
  }
  const url = `/reports?${new URLSearchParams({ ...filters, page: '3' })}`
  const { list, router, calls } = await setup(t, url, fetcher)
  function assertPage(page, ids) {
    const query = { ...filters, ...(page > 1 ? { page: String(page) } : {}) }
    assert.deepEqual(router.currentRoute.value.query, query)
    assert.deepEqual(calls.at(-1).params, query)
    assert.equal(list.meta.value.current_page, page)
    assert.equal(list.meta.value.total, 101)
    assert.equal(list.meta.value.last_page, 6)
    assert.deepEqual(list.reports.value.map(record => record.id), ids)
    assert.ok(list.reports.value.every(record => Object.entries(filters)
      .every(([field, value]) => record[field] === value)))
  }
  const middleIds = Array.from({ length: 20 }, (_, index) => 61 - index)
  const firstIds = Array.from({ length: 20 }, (_, index) => 101 - index)
  assertPage(3, middleIds)
  await list.setPage(list.meta.value.last_page)
  await settle()
  assertPage(6, [1])
  await list.setPage(1)
  await settle()
  assertPage(1, firstIds)
  router.back()
  await settle()
  assertPage(6, [1])
  router.back()
  await settle()
  assertPage(3, middleIds)
  router.forward()
  await settle()
  assertPage(6, [1])
  router.forward()
  await settle()
  assertPage(1, firstIds)
})

test('detail and deletion return target retain page and filters, including after URL reload', async t => {
  const { list, router } = await setup(t, '/reports?page=2&site_name=A&severity=high&status=completed')
  await router.push(list.detailLink(42))
  const query = router.currentRoute.value.query
  // Same query builder used by ReportDetailView for its back link and deleted event.
  const back = { path: '/reports', query: reportListQuery(readReportFilters(query), readReportPage(query)) }
  assert.deepEqual(back.query, { page: '2', site_name: 'A', severity: 'high', status: 'completed' })
  await router.push(back)
  await settle()
  assert.equal(list.meta.value.current_page, 2)
  const reloaded = await setup(t, router.currentRoute.value.fullPath)
  assert.equal(reloaded.list.meta.value.current_page, 2)
  assert.equal(reloaded.list.filters.value.site_name, 'A')
  assert.equal(reloaded.list.filters.value.status, 'completed')
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
    const { list, router, calls } = await setup(t, '/reports?page=8&severity=high', params => response(Number(params.page || 1), total))
    assert.equal(router.currentRoute.value.query.page, total ? '2' : undefined)
    assert.equal(list.meta.value.total, total)
    assert.deepEqual(list.reports.value, total ? [{ id: 2 }] : [])
    assert.equal(calls.length, 2)
    assert.equal(calls[1].params.severity, 'high')
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
