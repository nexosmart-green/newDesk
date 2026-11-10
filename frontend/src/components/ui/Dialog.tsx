import { useEffect, useId, useRef, type ReactNode } from 'react'

interface DialogProps {
  title: string
  onClose: () => void
  children: ReactNode
}

/** Minimal accessible modal: labelled, Escape closes, focus moves inside and returns on close. */
export function Dialog({ title, onClose, children }: DialogProps) {
  const titleId = useId()
  const panelRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const previouslyFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null
    panelRef.current?.querySelector<HTMLElement>('input, select, textarea, button')?.focus()
    return () => previouslyFocused?.focus()
  }, [])

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center sm:p-4">
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        onKeyDown={(event) => {
          if (event.key === 'Escape') onClose()
        }}
        className="max-h-[90vh] w-full max-w-md space-y-4 overflow-y-auto rounded-t-lg border border-border bg-surface p-5 sm:rounded-lg"
      >
        <h2 id={titleId} className="text-lg font-semibold">
          {title}
        </h2>
        {children}
      </div>
    </div>
  )
}
