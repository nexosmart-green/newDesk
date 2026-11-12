import type { ReactNode } from 'react'

interface StaleContentProps {
  /** True while the previous filter's or page's results stay on screen and the new ones load (`isPlaceholderData`). */
  stale: boolean
  children: ReactNode
  className?: string
}

/**
 * Dims what is on screen while it is being replaced, instead of a skeleton: no jump, no layout change, and
 * only this part of the page looks busy. Screen readers hear it through `aria-busy`.
 */
export function StaleContent({ stale, children, className = '' }: StaleContentProps) {
  return (
    <div aria-busy={stale || undefined} className={`transition-opacity duration-150 ${stale ? 'opacity-60' : ''} ${className}`}>
      {children}
    </div>
  )
}
