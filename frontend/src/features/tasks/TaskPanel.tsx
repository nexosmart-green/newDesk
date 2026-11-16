import { useId, type ReactNode } from 'react'

/**
 * A titled part of the task's main column (Descripción, Subtareas…). The column is one surface: parts are
 * told apart by their heading and the space between them, not by boxes of their own.
 */
export function Panel({ title, action, children }: { title: string; action?: ReactNode; children: ReactNode }) {
  const headingId = useId()
  return (
    <section aria-labelledby={headingId} className="space-y-3">
      <div className="flex min-h-9 flex-wrap items-center justify-between gap-2">
        <h2 id={headingId} className="text-base font-semibold">
          {title}
        </h2>
        {action}
      </div>
      {children}
    </section>
  )
}
