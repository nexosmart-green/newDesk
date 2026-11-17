import { useEffect, useState } from 'react'

// Vista previa de una imagen elegida: un data: URL (no blob:) porque la CSP del panel solo deja img-src 'self' data:.
export function useDataUrl(file: File | null): string | null {
  const [loaded, setLoaded] = useState<{ file: File; url: string } | null>(null)
  useEffect(() => {
    if (!file) return
    let active = true
    const reader = new FileReader()
    reader.onload = () => {
      if (active) setLoaded({ file, url: String(reader.result) })
    }
    reader.readAsDataURL(file)
    return () => {
      active = false
    }
  }, [file])
  return file && loaded?.file === file ? loaded.url : null
}
