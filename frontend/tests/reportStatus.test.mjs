import assert from 'node:assert/strict'
import test from 'node:test'
import { reportStatuses } from '../src/lib/reportOptions.ts'
import { useReportStatus } from '../src/lib/useReportStatus.ts'
import { readReportFilters, reportListQuery } from '../src/lib/reports.ts'

function deferred() {
  let resolve, reject
  const promise = new Promise((done, fail) => { resolve = done; reject = fail })
  return { promise, resolve, reject }
}

test('shared status definitions map four API values to distinct labels and Bootstrap badge colors', () => {
  assert.deepEqual(reportStatuses.map(({ value, label }) => [value, label]), [
    ['pending', '待處理'], ['in_progress', '處理中'], ['completed', '已完成'], ['reviewed', '已審核'],
  ])
  assert.equal(new Set(reportStatuses.map(option => option.badgeClass)).size, 4)
  for (const option of reportStatuses) assert.match(option.badgeClass, /^text-bg-/)
})

test('status filter survives URL round-trip and first page omits only the page parameter', () => {
  const filters = readReportFilters({ site_name: 'A', issue_type: '水電', severity: 'low', status: 'reviewed' })
  const query = reportListQuery(filters, 2)
  assert.equal(query.page, '2')
  assert.deepEqual(readReportFilters(Object.fromEntries(new URLSearchParams(query))), filters)
  assert.deepEqual(reportListQuery(filters), { site_name: 'A', issue_type: '水電', severity: 'low', status: 'reviewed' })
  assert.deepEqual(reportListQuery(readReportFilters({ status: '' })), {})
})

test('successful updates use the returned record, update the selection, and show feedback', async () => {
  const returned = { id: 3, status: 'reviewed', images: [{ id: 7 }], updated_at: '2026-11-09T00:00:00Z' }
  const calls = []
  const editor = useReportStatus('pending', async (status, signal) => { calls.push({ status, signal }); return returned })
  editor.selected.value = 'reviewed'
  assert.deepEqual(await editor.save(), returned)
  assert.equal(calls.length, 1)
  assert.equal(calls[0].status, 'reviewed')
  assert.equal(editor.current.value, 'reviewed')
  assert.equal(editor.selected.value, 'reviewed')
  assert.equal(editor.pending.value, false)
  assert.equal(editor.error.value, '')
  assert.match(editor.success.value, /已更新/)
})

test('all status transitions are allowed without a sequence requirement', async () => {
  for (const from of reportStatuses) {
    for (const to of reportStatuses) {
      let requests = 0
      const editor = useReportStatus(from.value, async status => { requests++; return { status } })
      editor.selected.value = to.value
      await editor.save()
      assert.equal(editor.current.value, to.value)
      assert.equal(requests, from.value === to.value ? 0 : 1)
    }
  }
})

test('unchanged selections and duplicate submissions do not send another request', async () => {
  const request = deferred()
  let count = 0
  const editor = useReportStatus('pending', () => { count++; return request.promise })
  assert.equal(await editor.save(), null)
  assert.equal(count, 0)
  editor.selected.value = 'in_progress'
  const saving = editor.save()
  assert.equal(editor.pending.value, true)
  assert.equal(await editor.save(), null)
  assert.equal(count, 1)
  request.resolve({ status: 'in_progress' })
  await saving
  assert.equal(editor.pending.value, false)
})

test('API and network failures restore the last saved status and allow a successful retry', async () => {
  for (const [failure, message] of [
    [{ isAxiosError: true, response: { status: 422 } }, /無效/],
    [{ isAxiosError: true, response: { status: 404 } }, /找不到/],
    [{ isAxiosError: true, response: { status: 429 } }, /頻繁/],
    [{ isAxiosError: true, response: { status: 500 } }, /重新載入/],
    [new Error('offline'), /重新載入/],
  ]) {
    let fail = true
    const editor = useReportStatus('completed', async status => { if (fail) throw failure; return { status } })
    editor.selected.value = 'pending'
    assert.equal(await editor.save(), null)
    assert.equal(editor.selected.value, 'completed')
    assert.equal(editor.current.value, 'completed')
    assert.equal(editor.pending.value, false)
    assert.match(editor.error.value, message)
    assert.equal(editor.success.value, '')
    fail = false
    editor.selected.value = 'reviewed'
    await editor.save()
    assert.equal(editor.error.value, '')
    assert.equal(editor.current.value, 'reviewed')
  }
})

test('a failed update after a successful update restores the most recently saved status', async () => {
  let fail = false
  const editor = useReportStatus('pending', async status => { if (fail) throw new Error('offline'); return { status } })
  editor.selected.value = 'completed'
  await editor.save()
  fail = true
  editor.selected.value = 'reviewed'
  await editor.save()
  assert.equal(editor.current.value, 'completed')
  assert.equal(editor.selected.value, 'completed')
  assert.equal(editor.success.value, '')
})

test('leaving the detail view cancels the request and ignores late success or failure', async () => {
  for (const fail of [false, true]) {
    const request = deferred()
    let signal
    const editor = useReportStatus('pending', (_, received) => { signal = received; return request.promise })
    editor.selected.value = 'reviewed'
    const task = editor.save()
    editor.cancel()
    assert.equal(signal.aborted, true)
    if (fail) request.reject(new Error('late'))
    else request.resolve({ status: 'reviewed' })
    assert.equal(await task, null)
    assert.equal(editor.current.value, 'pending')
    assert.equal(editor.success.value, '')
    assert.equal(editor.error.value, '')
    assert.equal(editor.pending.value, false)
  }
})
