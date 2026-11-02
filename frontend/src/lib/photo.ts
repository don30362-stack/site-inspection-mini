export const MAX_PHOTO_BYTES = 20 * 1024 * 1024

type PhotoFormat = 'jpeg' | 'png' | 'gif' | 'webp' | 'heif' | null

// Inspect content before loading the HEIF decoder; accept/name/MIME are only hints.
export function detectPhotoFormat(bytes: Uint8Array): PhotoFormat {
  const text = (start: number, length: number) =>
    String.fromCharCode(...bytes.slice(start, start + length))

  if (bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff) return 'jpeg'
  if (text(0, 8) === '\x89PNG\r\n\x1a\n') return 'png'
  if (['GIF87a', 'GIF89a'].includes(text(0, 6))) return 'gif'
  if (text(0, 4) === 'RIFF' && text(8, 4) === 'WEBP') return 'webp'

  if (bytes.length >= 16 && text(4, 4) === 'ftyp') {
    const boxSize = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength).getUint32(0)
    const brands = [text(8, 4)]
    for (let offset = 16; offset + 4 <= Math.min(boxSize, bytes.length); offset += 4) {
      brands.push(text(offset, 4))
    }
    if (brands.some((brand) => ['avif', 'avis'].includes(brand))) return null
    if (brands.some((brand) => ['heic', 'heix', 'hevc', 'hevx', 'heim', 'heis', 'hevm', 'hevs', 'mif1', 'msf1'].includes(brand))) {
      return 'heif'
    }
  }
  return null
}

export async function verifyPreview(file: Blob): Promise<void> {
  const url = URL.createObjectURL(file)
  try {
    await new Promise<void>((resolve, reject) => {
      const image = new Image()
      image.onload = () => image.naturalWidth > 0 ? resolve() : reject(new Error('圖片內容無法讀取。'))
      image.onerror = () => reject(new Error('圖片內容無法讀取，請重新拍照或選取其他照片。'))
      image.src = url
    })
  } finally {
    URL.revokeObjectURL(url)
  }
}

export async function preparePhoto(source: File): Promise<{ file: File; converted: boolean }> {
  if (source.size === 0) throw new Error('照片是空白檔案，請重新選取。')
  if (source.size > MAX_PHOTO_BYTES) throw new Error('照片不得超過 20 MiB，請選取較小的照片。')
  const format = detectPhotoFormat(new Uint8Array(await source.slice(0, 256).arrayBuffer()))
  if (!format) throw new Error('請選取 JPEG、PNG、GIF、WebP 或 HEIC／HEIF 圖片。')

  let file = source
  if (format === 'heif') {
    try {
      const { heicTo } = await import('heic-to')
      const jpeg = await heicTo({ blob: source, type: 'image/jpeg', quality: 0.85 })
      if (jpeg.size > MAX_PHOTO_BYTES) throw new Error('轉換後的照片超過 20 MiB，請選取較小的照片。')
      const header = new Uint8Array(await jpeg.slice(0, 16).arrayBuffer())
      if (detectPhotoFormat(header) !== 'jpeg') throw new Error('轉換結果不是有效的 JPEG。')
      file = new File([jpeg], 'photo.jpg', { type: 'image/jpeg', lastModified: source.lastModified })
    } catch (error) {
      if (error instanceof Error && error.message.includes('20 MiB')) throw error
      throw new Error('這張 HEIC／HEIF 照片無法自動處理，請重新拍照或選取其他照片；原檔不會上傳。')
    }
  } else {
    const mime = `image/${format}`
    // Preserve bytes, normalize name/type from content for empty or incorrect mobile MIME hints.
    file = new File([source], `photo.${format === 'jpeg' ? 'jpg' : format}`, { type: mime, lastModified: source.lastModified })
  }
  await verifyPreview(file)
  return { file, converted: format === 'heif' }
}
