import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import test from 'node:test'
import { detectPhotoFormat, MAX_PHOTO_BYTES, preparePhoto } from '../src/lib/photo.ts'

for (const [extension, format] of [['jpg', 'jpeg'], ['png', 'png'], ['gif', 'gif'], ['webp', 'webp']]) {
  test(`detects actual ${format} fixture content without filename or MIME`, async () => {
    const bytes = await readFile(new URL(`../../backend/tests/Fixtures/photo.${extension}`, import.meta.url))
    assert.equal(detectPhotoFormat(bytes), format)
  })
}

test('rejects text and SVG disguised as photos', () => {
  for (const text of ['not a JPEG', '<svg xmlns="http://www.w3.org/2000/svg"></svg>']) {
    assert.equal(detectPhotoFormat(new TextEncoder().encode(text)), null)
  }
})

test('recognizes HEIF compatible brands and rejects AVIF', () => {
  const header = new Uint8Array(24)
  new DataView(header.buffer).setUint32(0, 24)
  header.set(new TextEncoder().encode('ftypmif1'), 4)
  header.set(new TextEncoder().encode('heic'), 16)
  assert.equal(detectPhotoFormat(header), 'heif')
  header.set(new TextEncoder().encode('avif'), 16)
  assert.equal(detectPhotoFormat(header), null)
})

test('rejects empty or truncated format headers', () => {
  assert.equal(detectPhotoFormat(new Uint8Array()), null)
  assert.equal(detectPhotoFormat(new Uint8Array([0xff, 0xd8])), null)
})

test('rejects an empty input before any decoder import', async () => {
  await assert.rejects(preparePhoto(new File([], 'fake.heic')), /空白檔案/)
})

test('rejects an input exceeding 20 MiB before any decoder import', async () => {
  await assert.rejects(preparePhoto(new File([new Uint8Array(MAX_PHOTO_BYTES + 1)], 'large.heic')), /20 MiB/)
})
