import assert from 'node:assert/strict'
import test from 'node:test'
import { useLatestRequest } from '../src/lib/useLatestRequest.ts'

function deferred() {
  let resolve, reject
  const promise = new Promise((done, fail) => { resolve = done; reject = fail })
  return { promise, resolve, reject }
}

test('a late older success cannot replace a newer response even if abort is ignored', async () => {
  const request = useLatestRequest()
  const older = deferred()
  let firstSignal
  const first = request.run((signal) => { firstSignal = signal; return older.promise })
  assert.equal(request.loading.value, true)
  await request.run(async () => ['newest'])
  assert.equal(firstSignal.aborted, true)
  older.resolve(['old'])
  await first
  assert.deepEqual(request.data.value, ['newest'])
  assert.equal(request.loading.value, false)
})

test('older errors do not override newer data or loading state', async () => {
  const request = useLatestRequest()
  const older = deferred()
  const first = request.run(() => older.promise)
  await request.run(async () => ['current'])
  older.reject(new Error('stale network error'))
  await first
  assert.equal(request.error.value, '')
  assert.deepEqual(request.data.value, ['current'])
})

test('network failure can be retried without retaining an error or stale data', async () => {
  const request = useLatestRequest()
  await request.run(async () => { throw new Error('offline') })
  assert.match(request.error.value, /重新載入/)
  assert.equal(request.data.value, null)
  const retry = deferred()
  const task = request.run(() => retry.promise)
  assert.equal(request.error.value, '')
  assert.equal(request.loading.value, true)
  retry.resolve([])
  await task
  assert.deepEqual(request.data.value, [])
})

test('API 404 becomes a not-found state and is cleared by another successful request', async () => {
  const request = useLatestRequest()
  await request.run(async () => { throw { isAxiosError: true, response: { status: 404 } } })
  assert.equal(request.notFound.value, true)
  assert.equal(request.error.value, '')
  await request.run(async () => ({ id: 1 }))
  assert.equal(request.notFound.value, false)
  assert.deepEqual(request.data.value, { id: 1 })
})

test('cancel on unmount prevents a pending result from changing page state', async () => {
  const request = useLatestRequest()
  const pending = deferred()
  const task = request.run(() => pending.promise)
  request.cancel()
  pending.resolve(['late'])
  await task
  assert.equal(request.data.value, null)
  assert.equal(request.loading.value, false)
})
