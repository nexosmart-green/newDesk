import type { Tone } from './status'

// Umbrales de Google: puntaje de Lighthouse y Core Web Vitals (D-043).
export type Rating = 'good' | 'needs-improvement' | 'poor'

export const ratingMeta: Record<Rating, { label: string; tone: Tone }> = {
  good: { label: 'Bueno', tone: 'ok' },
  'needs-improvement': { label: 'A mejorar', tone: 'warn' },
  poor: { label: 'Malo', tone: 'crit' },
}

// 0–49 rojo, 50–89 naranja, 90–100 verde, como PageSpeed Insights.
export function scoreTone(score: number): Tone {
  if (score >= 90) return 'ok'
  if (score >= 50) return 'warn'
  return 'crit'
}

function rate(value: number, good: number, poor: number): Rating {
  if (value <= good) return 'good'
  if (value <= poor) return 'needs-improvement'
  return 'poor'
}

export const rateLCP = (ms: number): Rating => rate(ms, 2500, 4000)
export const rateINP = (ms: number): Rating => rate(ms, 200, 500)
export const rateCLS = (value: number): Rating => rate(value, 0.1, 0.25)

const secondsFormat = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })
const clsFormat = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

// LCP en segundos ("2,3 s"), INP en ms ("180 ms"), CLS sin unidad ("0,07").
export const formatLCP = (ms: number): string => `${secondsFormat.format(ms / 1000)} s`
export const formatINP = (ms: number): string => `${Math.round(ms)} ms`
export const formatCLS = (value: number): string => clsFormat.format(value)
