import type { ReactNode } from 'react'

interface PageHeaderProps {
  /** The page's only title (the header bar doesn't repeat it). */
  title: ReactNode
  /** One muted line under the title, when the title alone doesn't say what the page is for. */
  description?: ReactNode
  /** Page-level actions, right-aligned on desktop (primary last). */
  actions?: ReactNode
}

export function PageHeader({ title, description, actions }: PageHeaderProps) {
  return (
    <header className="flex flex-wrap items-end justify-between gap-x-4 gap-y-3">
      <div className="min-w-0 space-y-1">
        <h1 className="text-xl font-semibold leading-tight tracking-tight text-balance">{title}</h1>
        {description && <p className="text-sm text-muted">{description}</p>}
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </header>
  )
}
