interface CropRect {
  sx: number
  sy: number
  side: number
}

export const MIN_ZOOM = 1
export const MAX_ZOOM = 3
/** Backend resizes to 256px and 64px; 512px leaves room for retina without a heavy upload. */
const OUTPUT_SIZE = 512

/** Centered square of the image; zoom > 1 tightens it around the center. */
export function centeredSquareCrop(width: number, height: number, zoom: number): CropRect {
  const clampedZoom = Math.min(Math.max(zoom, MIN_ZOOM), MAX_ZOOM)
  const side = Math.min(width, height) / clampedZoom
  return { sx: (width - side) / 2, sy: (height - side) / 2, side }
}

export function drawSquareCrop(canvas: HTMLCanvasElement, image: HTMLImageElement, zoom: number): boolean {
  const context = canvas.getContext('2d')
  if (!context) return false
  const { sx, sy, side } = centeredSquareCrop(image.naturalWidth, image.naturalHeight, zoom)
  context.clearRect(0, 0, canvas.width, canvas.height)
  context.drawImage(image, sx, sy, side, side, 0, 0, canvas.width, canvas.height)
  return true
}

function canvasToBlob(canvas: HTMLCanvasElement, type: string): Promise<Blob | null> {
  return new Promise((resolve) => canvas.toBlob(resolve, type, 0.9))
}

/** WebP when the browser can encode it (Safari can't always), JPEG otherwise. */
export async function exportSquareCrop(image: HTMLImageElement, zoom: number): Promise<{ blob: Blob; fileName: string } | null> {
  const canvas = document.createElement('canvas')
  canvas.width = OUTPUT_SIZE
  canvas.height = OUTPUT_SIZE
  if (!drawSquareCrop(canvas, image, zoom)) return null

  const webp = await canvasToBlob(canvas, 'image/webp')
  if (webp?.type === 'image/webp') return { blob: webp, fileName: 'avatar.webp' }
  const jpeg = await canvasToBlob(canvas, 'image/jpeg')
  return jpeg ? { blob: jpeg, fileName: 'avatar.jpg' } : null
}
