import { useEffect, useId, useRef, useState } from 'react'
import { Link } from 'react-router'
import { useCurrentUser, useLogout } from '@/features/auth/useAuth'

const itemClassName = 'block w-full px-3 py-2 text-left text-sm hover:bg-bg focus-visible:bg-bg focus-visible:outline-none'

/** Disclosure menu under the user's name: Perfil · Seguridad · Salir. */
export function UserMenu() {
  const { data: user } = useCurrentUser()
  const logoutMutation = useLogout()
  const [open, setOpen] = useState(false)
  const menuId = useId()
  const containerRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!open) return
    function closeOnOutsideClick(event: MouseEvent) {
      if (!containerRef.current?.contains(event.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', closeOnOutsideClick)
    return () => document.removeEventListener('mousedown', closeOnOutsideClick)
  }, [open])

  const close = () => setOpen(false)

  return (
    <div
      ref={containerRef}
      className="relative"
      onKeyDown={(event) => {
        if (event.key === 'Escape') close()
      }}
    >
      <button
        type="button"
        aria-expanded={open}
        aria-controls={menuId}
        onClick={() => setOpen(!open)}
        className="text-sm text-muted hover:text-text"
      >
        {user?.full_name} <span aria-hidden="true">▾</span>
      </button>
      {open && (
        <ul id={menuId} className="absolute right-0 z-40 mt-2 w-40 overflow-hidden rounded border border-border bg-surface shadow-lg">
          <li>
            <Link to="/perfil" onClick={close} className={itemClassName}>
              Mi perfil
            </Link>
          </li>
          <li>
            <Link to="/seguridad" onClick={close} className={itemClassName}>
              Seguridad
            </Link>
          </li>
          <li>
            <button type="button" onClick={() => logoutMutation.mutate()} className={itemClassName}>
              Salir
            </button>
          </li>
        </ul>
      )}
    </div>
  )
}
