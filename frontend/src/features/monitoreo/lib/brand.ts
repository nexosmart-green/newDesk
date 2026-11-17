import type { CSSProperties } from 'react'

const hexColor = /^#[0-9a-f]{6}$/i

// Luminancia relativa (WCAG) de un #rrggbb.
function luminance(hex: string): number {
  const channel = (offset: number) => {
    const value = parseInt(hex.slice(offset, offset + 2), 16) / 255
    return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4
  }
  return 0.2126 * channel(1) + 0.7152 * channel(3) + 0.0722 * channel(5)
}

// La tinta (texto sobre el color de la marca) que más contrasta: negro o blanco.
export function inkFor(hex: string): '#000000' | '#ffffff' {
  const light = luminance(hex)
  return (light + 0.05) / 0.05 > 1.05 / (light + 0.05) ? '#000000' : '#ffffff'
}

// Pisa los tokens del acento con el color del cliente. Los colores de estado no cambian.
// --color-accent también: en newDesk bg-accent lee esa variable, que la piel resuelve una sola vez en [data-skin].
export function brandStyle(color: string | null | undefined): CSSProperties | undefined {
  if (!color || !hexColor.test(color)) return undefined
  return {
    '--accent': color,
    '--color-accent': color,
    '--accent-ink': inkFor(color),
    '--accent-soft': `color-mix(in oklab, ${color} 14%, transparent)`,
    '--chart-1': color,
  } as CSSProperties
}
