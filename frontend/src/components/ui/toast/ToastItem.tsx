import { CircleAlert, CircleCheck, Info, X, type LucideIcon } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { toast as toasts, type Toast, type ToastVariant } from './toastStore'

const looks: Record<ToastVariant, { icon: LucideIcon; iconClass: string; ring: string; label: string }> = {
  success: { icon: CircleCheck, iconClass: 'text-success', ring: 'ring-success/30', label: 'Listo' },
  error: { icon: CircleAlert, iconClass: 'text-danger', ring: 'ring-danger/40', label: 'Error' },
  info: { icon: Info, iconClass: 'text-accent', ring: 'ring-brand/30', label: 'Aviso' },
}

/** One toast: leaves after its duration, the countdown pauses while hovered or focused. */
export function ToastItem({ toast }: { toast: Toast }) {
  const [paused, setPaused] = useState(false)
  const remaining = useRef(toast.duration)

  useEffect(() => {
    if (paused) return
    const startedAt = Date.now()
    const timeout = setTimeout(() => toasts.dismiss(toast.id), remaining.current)
    return () => {
      clearTimeout(timeout)
      remaining.current = Math.max(800, remaining.current - (Date.now() - startedAt))
    }
  }, [paused, toast.id])

  const look = looks[toast.variant]
  const Icon = look.icon

  return (
    <li
      onMouseEnter={() => setPaused(true)}
      onMouseLeave={() => setPaused(false)}
      onFocus={() => setPaused(true)}
      onBlur={() => setPaused(false)}
      data-variant={toast.variant}
      className={`pointer-events-auto flex items-start gap-3 rounded-xl border border-border bg-surface py-3 pl-4 pr-1 text-sm shadow-xl ring-1 [--toast-from:-12px] motion-safe:animate-[toast-in_200ms_cubic-bezier(0.2,0.8,0.2,1)] sm:[--toast-from:12px] ${look.ring}`}
    >
      <Icon aria-hidden="true" className={`mt-0.5 size-5 shrink-0 ${look.iconClass}`} strokeWidth={2} />
      <div className="min-w-0 flex-1 py-0.5">
        <span className="sr-only">{look.label}: </span>
        {toast.title && <p className="font-semibold leading-snug">{toast.title}</p>}
        <p className={`leading-snug ${toast.title ? 'mt-0.5 text-muted' : ''}`}>{toast.message}</p>
      </div>
      <button
        type="button"
        onClick={() => toasts.dismiss(toast.id)}
        aria-label="Cerrar aviso"
        className="-my-2 grid size-11 shrink-0 cursor-pointer place-items-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-text focus-visible:outline-2 focus-visible:outline-accent"
      >
        <X aria-hidden="true" className="size-4" />
      </button>
    </li>
  )
}
