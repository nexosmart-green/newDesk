import type { LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'

interface EmptyStateProps {
  icon: LucideIcon
  /** One sentence: what is (not) here, or what to do about it. */
  children: ReactNode
  /** At most one way out: clear the filters, create the first one, go where they come from. */
  action?: ReactNode
  /** Inside a section that already has its own surface: no box of its own. */
  bare?: boolean
  className?: string
}

/** Nothing to show — said once, plainly, with the next step at hand. A line in the log, not a poster. */
export function EmptyState({ icon: Icon, children, action, bare = false, className = '' }: EmptyStateProps) {
  return (
    <div
      className={`flex flex-col items-start gap-3 sm:flex-row sm:items-center ${bare ? 'py-2' : 'rounded-lg border border-line bg-surface px-4 py-4'} ${className}`}
    >
      <p className="flex min-w-0 flex-1 items-start gap-3 text-sm text-muted">
        <Icon aria-hidden="true" className="mt-px size-[18px] shrink-0" strokeWidth={1.75} />
        <span>{children}</span>
      </p>
      {action && <div className="shrink-0">{action}</div>}
    </div>
  )
}
