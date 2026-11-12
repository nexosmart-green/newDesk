import { X } from 'lucide-react'
import { useId, useRef, type ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { useModalFocus } from './useModalFocus'

type DialogSize = 'sm' | 'md' | 'lg' | 'xl'

interface DialogProps {
  title: string
  /** Optional one-liner under the title (what this dialog is for). */
  description?: ReactNode
  /** `md` (32rem) by default; forms with several fields use `lg`. */
  size?: DialogSize
  onClose: () => void
  children: ReactNode
}

const widths: Record<DialogSize, string> = {
  sm: 'sm:max-w-sm',
  md: 'sm:max-w-lg',
  lg: 'sm:max-w-2xl',
  xl: 'sm:max-w-4xl',
}

/** Big forms take the whole phone screen; short prompts stay a bottom sheet. */
const phoneShape: Record<DialogSize, string> = {
  sm: 'max-h-[92dvh] rounded-t-2xl',
  md: 'max-h-[92dvh] rounded-t-2xl',
  lg: 'h-dvh',
  xl: 'h-dvh',
}

/**
 * The one modal primitive. Rendered through a portal on `document.body` so no ancestor (the header's
 * `backdrop-blur` creates a containing block for `fixed`) can clip it. Labelled, focus trapped,
 * Escape / X / scrim close it, focus returns to the trigger.
 */
export function Dialog({ title, description, size = 'md', onClose, children }: DialogProps) {
  const titleId = useId()
  const descriptionId = useId()
  const panelRef = useRef<HTMLDivElement>(null)
  useModalFocus(panelRef, onClose)

  return createPortal(
    <div
      className="fixed inset-0 z-50 flex items-end justify-center bg-black/55 motion-safe:animate-[scrim-in_150ms_ease-out] sm:items-center sm:p-6"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) onClose()
      }}
    >
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={description ? descriptionId : undefined}
        tabIndex={-1}
        className={`flex w-full flex-col overflow-hidden border border-border bg-surface shadow-2xl outline-none motion-safe:animate-[dialog-in_180ms_cubic-bezier(0.2,0.8,0.2,1)] sm:h-auto sm:max-h-[min(88dvh,56rem)] sm:rounded-2xl ${phoneShape[size]} ${widths[size]}`}
      >
        <div className="flex items-start gap-3 border-b border-border px-5 py-3 sm:px-6">
          <div className="min-w-0 flex-1 py-1.5">
            <h2 id={titleId} className="text-lg font-semibold leading-snug tracking-tight">
              {title}
            </h2>
            {description && (
              <p id={descriptionId} className="mt-0.5 text-sm text-muted">
                {description}
              </p>
            )}
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Cerrar"
            data-focus-guard
            className="-mr-2 grid size-11 shrink-0 cursor-pointer place-items-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-text focus-visible:outline-2 focus-visible:outline-accent"
          >
            <X aria-hidden="true" className="size-5" />
          </button>
        </div>
        <div className="flex min-h-0 flex-1 flex-col space-y-4 overflow-y-auto [&>form]:flex [&>form]:flex-1 [&>form]:flex-col overscroll-contain px-5 pb-5 pt-4 sm:px-6 has-[[data-dialog-footer]]:pb-0">
          {children}
        </div>
      </div>
    </div>,
    document.body,
  )
}

/**
 * Action row pinned to the bottom of the dialog while the body scrolls. Works inside a `<form>`,
 * so submit buttons keep submitting. Primary action goes last (right).
 */
export function DialogFooter({ children }: { children: ReactNode }) {
  return (
    <div
      data-dialog-footer
      className="sticky bottom-0 z-10 -mx-5 mt-auto flex flex-col-reverse gap-2 border-t border-border bg-surface px-5 pt-3 sm:-mx-6 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end sm:px-6 pb-[max(0.75rem,env(safe-area-inset-bottom))] [&>*]:w-full sm:[&>*]:w-auto"
    >
      {children}
    </div>
  )
}
