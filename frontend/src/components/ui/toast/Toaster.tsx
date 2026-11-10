import { createPortal } from 'react-dom'
import { ToastItem } from './ToastItem'
import { useToasts } from './useToast'

/**
 * Mounted once at the root. Phones: top of the screen (thumbs and the keyboard live at the bottom);
 * desktop: bottom-right, newest closest to the corner. Polite live region: never steals focus.
 */
export function Toaster() {
  const toasts = useToasts()

  return createPortal(
    <section
      aria-label="Avisos"
      className="pointer-events-none fixed inset-x-0 top-0 z-[70] flex justify-center px-3 pt-[max(0.75rem,env(safe-area-inset-top))] sm:inset-x-auto sm:bottom-0 sm:right-0 sm:top-auto sm:justify-end sm:p-6"
    >
      <ol aria-live="polite" aria-relevant="additions" className="flex w-full max-w-sm flex-col gap-2 sm:flex-col-reverse">
        {toasts.map((item) => (
          <ToastItem key={item.id} toast={item} />
        ))}
      </ol>
    </section>,
    document.body,
  )
}
