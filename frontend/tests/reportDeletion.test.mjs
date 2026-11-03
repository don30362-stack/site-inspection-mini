import assert from 'node:assert/strict'
import test from 'node:test'
import { useReportDeletion } from '../src/lib/useReportDeletion.ts'

test('requires a password and sends one confirmed request without keeping the password', async () => {
  let finish
  const calls = []
  const deletion = useReportDeletion((password, signal) => {
    calls.push({ password, signal })
    return new Promise((resolve) => { finish = resolve })
  })
  assert.equal(await deletion.submit(), false)
  assert.match(deletion.error.value, /請輸入/)
  deletion.password.value = 'test-only-password'
  const task = deletion.submit()
  assert.equal(deletion.pending.value, true)
  assert.equal(deletion.password.value, '')
  assert.equal(await deletion.submit(), false)
  assert.equal(calls.length, 1)
  assert.equal(calls[0].password, 'test-only-password')
  finish()
  assert.equal(await task, true)
  assert.equal(deletion.password.value, '')
})

test('wrong password, missing record, throttling, configuration and storage errors are not success', async () => {
  for (const [status, text] of [[403, /密碼錯誤/], [404, /找不到/], [422, /有效/], [429, /頻繁/], [503, /暫停/], [409, /正在刪除/], [500, /備份清理失敗/]]) {
    const deletion = useReportDeletion(async () => { throw { isAxiosError: true, response: { status, data: { message: '備份清理失敗' } } } })
    deletion.password.value = 'test-only-password'
    assert.equal(await deletion.submit(), false)
    assert.match(deletion.error.value, text)
    assert.equal(deletion.password.value, '')
  }
})

test('network failure is ambiguous and never retries automatically; cancel clears the secret', async () => {
  let calls = 0
  const deletion = useReportDeletion(async () => { calls++; throw { isAxiosError: true } })
  deletion.password.value = 'test-only-password'
  assert.equal(await deletion.submit(), false)
  assert.match(deletion.error.value, /尚未確認.*勿直接重複/)
  assert.equal(calls, 1)
  deletion.password.value = 'another-test-password'
  deletion.cancel()
  assert.equal(deletion.password.value, '')
})

test('shared capacity lock contention displays the photo-storage busy message', async () => {
  const message = '照片儲存忙碌中，請稍後再試。'
  const deletion = useReportDeletion(async () => { throw { isAxiosError: true, response: { status: 409, data: { message } } } })
  deletion.password.value = 'test-only-password'
  assert.equal(await deletion.submit(), false)
  assert.equal(deletion.error.value, message)
  assert.equal(deletion.password.value, '')
})

test('leaving the page prevents a late response from reporting success', async () => {
  let finish
  const deletion = useReportDeletion(() => new Promise((resolve) => { finish = resolve }))
  deletion.password.value = 'test-only-password'
  const task = deletion.submit()
  deletion.cancel()
  finish()
  assert.equal(await task, false)
  assert.equal(deletion.password.value, '')
})
