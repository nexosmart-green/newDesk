import type { ReactNode } from 'react'
import { badgeClassName, type BadgeTone } from './badgeStyles'

export type { BadgeTone } from './badgeStyles'

export function Badge({ tone = 'neutral', children }: { tone?: BadgeTone; children: ReactNode }) {
  return <span className={badgeClassName(tone)}>{children}</span>
}
