import { X } from 'lucide-react'
import { useEffect, useRef } from 'react'
import { Brand } from './Brand'
import type { NavGroup, NavItem } from './navigation'
import { SidebarNav } from './SidebarNav'

interface MobileDrawerProps {
  open: boolean
  onClose: () => void
  groups: NavGroup[]
  footer: NavItem[]
}

/** < lg: off-canvas navigation with a scrim; Escape or tapping outside closes it and focus returns. */
export function MobileDrawer({ open, onClose, groups, footer }: MobileDrawerProps) {
  const panelRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!open) return
    const previouslyFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null
    panelRef.current?.querySelector<HTMLElement>('a, button')?.focus()
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', onKeyDown)
    document.body.style.overflow = 'hidden'
    return () => {
      document.removeEventListener('keydown', onKeyDown)
      document.body.style.overflow = ''
      previouslyFocused?.focus()
    }
  }, [open, onClose])

  if (!open) return null

  return (
    <div className="fixed inset-0 z-50 lg:hidden">
      <div aria-hidden="true" className="absolute inset-0 bg-black/55" onClick={onClose} />
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-label="Navegación"
        className="absolute inset-y-0 left-0 flex w-72 max-w-[85vw] flex-col bg-sidebar shadow-2xl motion-safe:animate-[drawer-in_180ms_ease-out]"
      >
        <div className="flex h-16 items-center justify-between border-b border-white/10 px-4">
          <Brand />
          <button
            type="button"
            onClick={onClose}
            aria-label="Cerrar menú"
            className="grid size-11 place-items-center rounded-lg text-sidebar-muted hover:bg-sidebar-hover hover:text-white focus-visible:outline-2 focus-visible:outline-accent"
          >
            <X aria-hidden="true" className="size-5" />
          </button>
        </div>
        <nav aria-label="Principal" className="flex min-h-0 flex-1 flex-col">
          <SidebarNav groups={groups} footer={footer} collapsed={false} onNavigate={onClose} />
        </nav>
      </div>
    </div>
  )
}
