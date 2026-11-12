import { ChevronLeft, ChevronRight, Download, X } from 'lucide-react'
import { useId, useRef } from 'react'
import { createPortal } from 'react-dom'
import { useModalFocus } from './useModalFocus'

export interface LightboxImage {
  src: string
  name: string
}

interface LightboxProps {
  images: LightboxImage[]
  index: number
  onIndexChange: (index: number) => void
  onClose: () => void
}

const controlClass =
  'grid size-11 shrink-0 cursor-pointer place-items-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white'

/** Full-screen image viewer: ← → move between images, Escape (or the scrim) closes, focus stays inside. */
export function Lightbox({ images, index, onIndexChange, onClose }: LightboxProps) {
  const titleId = useId()
  const panelRef = useRef<HTMLDivElement>(null)
  const count = images.length
  const current = images[Math.min(index, count - 1)]
  const go = (step: number) => onIndexChange((index + step + count) % count)

  useModalFocus(panelRef, onClose, (event) => {
    if (count < 2) return
    if (event.key === 'ArrowRight') go(1)
    else if (event.key === 'ArrowLeft') go(-1)
  })

  if (!current) return null
  return createPortal(
    <div
      ref={panelRef}
      role="dialog"
      aria-modal="true"
      aria-labelledby={titleId}
      tabIndex={-1}
      className="fixed inset-0 z-50 flex flex-col bg-black/95 text-white outline-none motion-safe:animate-[scrim-in_150ms_ease-out]"
    >
      <div className="flex items-center gap-3 px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-3">
        <div className="min-w-0 flex-1">
          <h2 id={titleId} className="truncate text-sm font-medium">
            {current.name}
          </h2>
          {count > 1 && (
            <p className="tabular text-xs text-white/70" aria-live="polite">
              {index + 1} de {count}
            </p>
          )}
        </div>
        <a href={current.src} download={current.name} className={controlClass} aria-label={`Descargar ${current.name}`} title="Descargar">
          <Download aria-hidden="true" className="size-5" />
        </a>
        <button type="button" onClick={onClose} className={controlClass} aria-label="Cerrar visor" data-autofocus>
          <X aria-hidden="true" className="size-5" />
        </button>
      </div>
      <div
        className="relative flex min-h-0 flex-1 items-center justify-center px-2 pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-16"
        onMouseDown={(event) => {
          if (event.target === event.currentTarget) onClose()
        }}
      >
        <img key={current.src} src={current.src} alt={current.name} className="max-h-full max-w-full rounded-md object-contain shadow-2xl" />
        {count > 1 && (
          <>
            <button type="button" onClick={() => go(-1)} aria-label="Imagen anterior" className={`${controlClass} absolute top-1/2 left-2 -translate-y-1/2 sm:left-4`}>
              <ChevronLeft aria-hidden="true" className="size-6" />
            </button>
            <button type="button" onClick={() => go(1)} aria-label="Imagen siguiente" className={`${controlClass} absolute top-1/2 right-2 -translate-y-1/2 sm:right-4`}>
              <ChevronRight aria-hidden="true" className="size-6" />
            </button>
          </>
        )}
      </div>
    </div>,
    document.body,
  )
}
