export type BadgeTone = 'neutral' | 'success' | 'warning' | 'danger' | 'accent'

const tones: Record<BadgeTone, string> = {
  neutral: 'border-border text-muted',
  success: 'border-success/40 text-success',
  warning: 'border-warning/40 text-warning',
  danger: 'border-danger/40 text-danger',
  accent: 'border-accent/40 text-accent',
}

/** Also for something that looks like a badge but is a control (a status you can change). */
export function badgeClassName(tone: BadgeTone): string {
  return `inline-flex items-center gap-1 whitespace-nowrap rounded-full border px-2 py-0.5 text-xs font-medium ${tones[tone]}`
}
