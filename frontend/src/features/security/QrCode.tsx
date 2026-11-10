import QRCode from 'qrcode'
import { useEffect, useState } from 'react'

/** Renders the otpauth URL as an SVG QR (pure JS, no canvas needed). */
export function QrCode({ value, label }: { value: string; label: string }) {
  const [svg, setSvg] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false
    QRCode.toString(value, { type: 'svg', margin: 2, errorCorrectionLevel: 'M' })
      .then((markup) => {
        if (!cancelled) setSvg(markup)
      })
      .catch(() => {
        if (!cancelled) setSvg(null)
      })
    return () => {
      cancelled = true
    }
  }, [value])

  if (!svg) return <div className="size-48 animate-pulse rounded bg-border" aria-hidden="true" />

  return (
    <img
      src={`data:image/svg+xml;utf8,${encodeURIComponent(svg)}`}
      alt={label}
      width={192}
      height={192}
      className="size-48 rounded bg-white p-1"
    />
  )
}
