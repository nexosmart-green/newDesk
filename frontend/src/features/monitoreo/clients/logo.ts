// Mismas reglas que el back: PNG, JPEG o WebP de hasta 256 KB (el SVG queda afuera: puede llevar scripts).
export const LOGO_TYPES = ['image/png', 'image/jpeg', 'image/webp']
export const LOGO_MAX_BYTES = 256 * 1024

export function logoProblem(file: { type: string; size: number }): string | null {
  if (!LOGO_TYPES.includes(file.type)) return 'El logo tiene que ser PNG, JPG o WebP.'
  if (file.size > LOGO_MAX_BYTES) return 'El logo puede pesar hasta 256 KB.'
  return null
}

export function fileToBase64(file: Blob): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(String(reader.result).split(',')[1] ?? '')
    reader.onerror = () => reject(reader.error ?? new Error('No se pudo leer el archivo.'))
    reader.readAsDataURL(file)
  })
}
