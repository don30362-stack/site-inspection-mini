import assert from 'node:assert/strict'
import test from 'node:test'
import { usePhotoCollection } from '../src/lib/photoCollection.ts'

const file = (name) => new File(['image'], name, { type: 'image/jpeg' })
function setup(prepare = async (source) => ({ file: source, converted: false })) {
  const revoked = []
  let nextUrl = 0
  const urls = { createObjectURL: () => `blob:${++nextUrl}`, revokeObjectURL: (url) => revoked.push(url) }
  return { ...usePhotoCollection(prepare, urls), revoked }
}

test('adds five, rejects a sixth explicitly, deletes and fills without gaps', async () => {
  const photos = setup()
  await photos.addPhotos([1, 2, 3, 4, 5].map((n) => file(`${n}.jpg`)))
  assert.equal(photos.items.length, 5)
  await photos.addPhotos([file('6.jpg')])
  assert.match(photos.photoError.value, /最多.*5.*未加入/)
  assert.equal(photos.items.length, 5)
  photos.removePhoto(photos.items[1].key)
  assert.deepEqual(photos.items.map((entry) => entry.file.name), ['1.jpg', '3.jpg', '4.jpg', '5.jpg'])
  assert.deepEqual(photos.revoked, ['blob:2'])
  await photos.addPhotos([file('6.jpg')])
  assert.equal(photos.items.at(-1).file.name, '6.jpg')
})

test('a selection exceeding available slots is rejected as a whole', async () => {
  const photos = setup()
  await photos.addPhotos([file('a.jpg'), file('b.jpg')])
  await photos.addPhotos([file('c.jpg'), file('d.jpg'), file('e.jpg'), file('f.jpg')])
  assert.equal(photos.items.length, 2)
  assert.match(photos.photoError.value, /本次選取未加入/)
})

test('processes selections sequentially and shows queued and processing states', async () => {
  const resolvers = []
  let active = 0
  let maximum = 0
  const photos = setup(async (source) => {
    maximum = Math.max(maximum, ++active)
    await new Promise((resolve) => resolvers.push(resolve))
    active--
    return { file: source, converted: true }
  })
  const task = photos.addPhotos([file('a.heic'), file('b.heic')])
  assert.deepEqual(photos.items.map((entry) => entry.status), ['processing', 'queued'])
  assert.equal(photos.processingPhoto.value, true)
  resolvers.shift()()
  await new Promise((resolve) => setImmediate(resolve))
  assert.deepEqual(photos.items.map((entry) => entry.status), ['ready', 'processing'])
  resolvers.shift()()
  await task
  assert.equal(maximum, 1)
  assert.equal(photos.processingPhoto.value, false)
})

test('failed replacement preserves original File and URL; successful replacement revokes only the old URL', async () => {
  const photos = setup(async (source) => {
    if (source.name === 'bad.heic') throw new Error('轉換失敗')
    return { file: source, converted: false }
  })
  const original = file('original.jpg')
  await photos.addPhotos([original])
  const key = photos.items[0].key
  await photos.replacePhoto(key, file('bad.heic'))
  assert.equal(photos.items[0].file, original)
  assert.equal(photos.items[0].url, 'blob:1')
  assert.match(photos.items[0].error, /已保留原照片/)
  assert.deepEqual(photos.revoked, [])
  await photos.replacePhoto(key, file('new.jpg'))
  assert.equal(photos.items[0].file.name, 'new.jpg')
  assert.equal(photos.items[0].url, 'blob:2')
  assert.deepEqual(photos.revoked, ['blob:1'])
})

test('cancelled replacement keeps the original file, preview and position', async () => {
  const photos = setup()
  const original = file('original.jpg')
  await photos.addPhotos([original, file('second.jpg')])
  await photos.replacePhoto(photos.items[0].key, undefined)
  assert.equal(photos.items[0].file, original)
  assert.equal(photos.items[0].url, 'blob:1')
  assert.equal(photos.items.length, 2)
  assert.deepEqual(photos.revoked, [])
})

test('failed additions leave valid photos in sequence and report the failure', async () => {
  const photos = setup(async (source) => {
    if (source.name === 'bad.heic') throw new Error('無法轉換')
    return { file: source, converted: false }
  })
  await photos.addPhotos([file('a.jpg'), file('bad.heic'), file('b.jpg')])
  assert.deepEqual(photos.items.map((entry) => entry.file.name), ['a.jpg', 'b.jpg'])
  assert.match(photos.photoError.value, /bad.heic.*無法轉換/)
})

test('clearing during conversion prevents late previews and releases every existing URL', async () => {
  let finish
  const photos = setup(async (source) => {
    if (source.name === 'late.heic') await new Promise((resolve) => { finish = resolve })
    return { file: source, converted: false }
  })
  await photos.addPhotos([file('first.jpg')])
  const task = photos.replacePhoto(photos.items[0].key, file('late.heic'))
  photos.clearPhotos()
  finish()
  await task
  assert.equal(photos.items.length, 0)
  assert.deepEqual(photos.revoked, ['blob:1'])
})

test('cross-row reorder preserves objects, files, URLs, conversion and errors without decoding again', async () => {
  let calls = 0
  const photos = setup(async (source) => {
    calls++
    return { file: source, converted: source.name.endsWith('.heic') }
  })
  await photos.addPhotos([1, 2, 3, 4, 5].map((n) => file(`${n}.heic`)))
  photos.items[4].error = '保留的欄位錯誤'
  const original = [...photos.items]
  const snapshots = original.map((item) => ({ ...item }))
  assert.equal(photos.movePhoto(original[4].key, 0), true)
  assert.deepEqual(photos.items.map((item) => item.key), [4, 0, 1, 2, 3])
  assert.equal(photos.movePhoto(photos.items[1].key, 4), true)
  assert.deepEqual(photos.items.map((item) => item.key), [4, 1, 2, 3, 0])
  for (const item of photos.items) {
    assert.equal(item, original[item.key])
    assert.deepEqual({ ...item }, snapshots[item.key])
    assert.equal(item.file, snapshots[item.key].file)
  }
  assert.equal(calls, 5)
  assert.deepEqual(photos.revoked, [])
  // The form appends in items order; verify actual multipart File contents/order.
  const body = new FormData()
  for (const item of photos.items) body.append('photos[]', item.file)
  assert.deepEqual(body.getAll('photos[]').map((entry) => entry.name), ['5.heic', '2.heic', '3.heic', '4.heic', '1.heic'])
  await photos.replacePhoto(photos.items[1].key, file('replacement.jpg'))
  assert.equal(photos.items[1].file.name, 'replacement.jpg')
  photos.removePhoto(photos.items[2].key)
  await photos.addPhotos([file('new.jpg')])
  assert.deepEqual(photos.items.map((item) => item.file.name), ['5.heic', 'replacement.jpg', '4.heic', '1.heic', 'new.jpg'])
  assert.deepEqual(photos.revoked, ['blob:2', 'blob:3'])
  assert.equal(photos.movePhoto(photos.items[4].key, 0), true)
  photos.clearPhotos()
  assert.equal(new Set(photos.revoked).size, 7)
  assert.equal(photos.revoked.length, 7)
})

test('invalid, unchanged and processing reorder attempts leave the array untouched', async () => {
  let finish
  const photos = setup(async (source) => {
    if (source.name === 'pending.heic') await new Promise((resolve) => { finish = resolve })
    return { file: source, converted: false }
  })
  await photos.addPhotos([file('a.jpg'), file('b.jpg')])
  const original = [...photos.items]
  for (const [key, to] of [[0, 0], [0, -1], [0, 2], [0, 0.5], [99, 0]]) assert.equal(photos.movePhoto(key, to), false)
  assert.deepEqual([...photos.items], original)
  const task = photos.replacePhoto(0, file('pending.heic'))
  assert.equal(photos.movePhoto(1, 0), false)
  assert.deepEqual(photos.items.map((item) => item.key), [0, 1])
  finish()
  await task
  assert.equal(photos.movePhoto(1, 0), true)
})
