import type { ReactNode } from 'react'

/** Placeholders shaped like what is coming, so the page doesn't jump when it arrives. */
export function Skeleton({ className = '' }: { className?: string }) {
  return <div aria-hidden="true" className={`skeleton rounded-md ${className}`} />
}

function Loading({ label, children, className = '' }: { label: string; children: ReactNode; className?: string }) {
  return (
    <div role="status" aria-live="polite" className={className}>
      <span className="sr-only">{label}</span>
      {children}
    </div>
  )
}

/** Rows like a task list: code + title, then a line of meta. */
export function ListSkeleton({ rows = 5, label = 'Cargando…' }: { rows?: number; label?: string }) {
  return (
    <Loading label={label} className="divide-y divide-border overflow-hidden rounded-lg border border-border bg-surface">
      {Array.from({ length: rows }, (_, index) => (
        <div key={index} className="flex items-center gap-3 p-3">
          <div className="min-w-0 flex-1 space-y-2">
            <Skeleton className={`h-4 ${index % 2 === 0 ? 'w-3/5' : 'w-2/5'}`} />
            <Skeleton className="h-3 w-1/3" />
          </div>
          <Skeleton className="size-11 shrink-0 rounded-lg" />
        </div>
      ))}
    </Loading>
  )
}

export function CardGridSkeleton({ cards = 6, label = 'Cargando…' }: { cards?: number; label?: string }) {
  return (
    <Loading label={label} className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      {Array.from({ length: cards }, (_, index) => (
        <div key={index} className="flex items-center gap-3 rounded-lg border border-border bg-surface p-4">
          <Skeleton className="size-10 shrink-0 rounded-full" />
          <div className="flex-1 space-y-2">
            <Skeleton className="h-4 w-2/3" />
            <Skeleton className="h-3 w-1/2" />
          </div>
        </div>
      ))}
    </Loading>
  )
}

export function TableSkeleton({ rows = 6, columns = 4, label = 'Cargando…' }: { rows?: number; columns?: number; label?: string }) {
  return (
    <Loading label={label} className="overflow-hidden rounded-lg border border-border bg-surface">
      <div className="flex gap-4 border-b border-border px-4 py-3">
        {Array.from({ length: columns }, (_, index) => (
          <Skeleton key={index} className="h-3 flex-1" />
        ))}
      </div>
      {Array.from({ length: rows }, (_, row) => (
        <div key={row} className="flex gap-4 px-4 py-3">
          {Array.from({ length: columns }, (_, column) => (
            <Skeleton key={column} className={`h-4 flex-1 ${column === 0 ? 'max-w-[40%]' : ''}`} />
          ))}
        </div>
      ))}
    </Loading>
  )
}

/** A detail page: title, a meta line, then a block of content. */
export function DetailSkeleton({ label = 'Cargando…' }: { label?: string }) {
  return (
    <Loading label={label} className="space-y-4">
      <Skeleton className="h-7 w-1/2 max-w-sm" />
      <Skeleton className="h-4 w-1/3 max-w-xs" />
      <div className="space-y-3 rounded-lg border border-border bg-surface p-4">
        <Skeleton className="h-4 w-full" />
        <Skeleton className="h-4 w-11/12" />
        <Skeleton className="h-4 w-4/5" />
      </div>
    </Loading>
  )
}
