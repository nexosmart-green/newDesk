import { ChevronDown, KeyRound, LogOut, Moon, Sparkles, Sun, UserRound } from 'lucide-react'
import { useEffect, useId, useRef, useState, type ReactNode } from 'react'
import { Link } from 'react-router'
import { useCurrentUser, useLogout } from '@/features/auth/useAuth'

const itemClassName =
  'flex min-h-11 w-full items-center gap-3 px-3 text-left text-sm text-text hover:bg-surface-2 focus-visible:bg-surface-2 focus-visible:outline-none'

const initials = (firstName = '', lastName = '') => `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase() || '?'

interface UserMenuProps {
  theme: 'dark' | 'light'
  onToggleTheme: () => void
}

function MenuLink({ to, icon, children, onClick }: { to: string; icon: ReactNode; children: ReactNode; onClick: () => void }) {
  return (
    <li>
      <Link to={to} onClick={onClick} className={itemClassName}>
        {icon}
        {children}
      </Link>
    </li>
  )
}

/** Account menu: profile, AI connections (RF-12), security, theme (on phones) and — set apart — logout. */
export function UserMenu({ theme, onToggleTheme }: UserMenuProps) {
  const { data: user } = useCurrentUser()
  const logoutMutation = useLogout()
  const [open, setOpen] = useState(false)
  const menuId = useId()
  const containerRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!open) return
    function closeOnOutsideClick(event: PointerEvent) {
      if (event.target instanceof Node && !containerRef.current?.contains(event.target)) setOpen(false)
    }
    document.addEventListener('pointerdown', closeOnOutsideClick)
    return () => document.removeEventListener('pointerdown', closeOnOutsideClick)
  }, [open])

  const close = () => setOpen(false)
  const iconClass = 'size-4 text-muted'

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
        aria-label={`Cuenta de ${user?.full_name ?? 'usuario'}`}
        onClick={() => setOpen(!open)}
        className="flex min-h-11 items-center gap-2 rounded-lg px-1.5 hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-accent sm:px-2"
      >
        <span aria-hidden="true" className="grid size-8 place-items-center rounded-full bg-brand-strong text-xs font-semibold text-white">
          {initials(user?.first_name, user?.last_name)}
        </span>
        <span className="hidden text-left leading-tight md:block">
          <span className="block text-sm font-medium">{user?.full_name}</span>
          <span className="block text-xs text-muted">{user?.role.name}</span>
        </span>
        <ChevronDown aria-hidden="true" className="hidden size-4 text-muted md:block" />
      </button>
      {open && (
        <div id={menuId} className="absolute right-0 z-50 mt-2 w-60 overflow-hidden rounded-xl border border-border bg-surface shadow-xl">
          <div className="border-b border-border px-3 py-3">
            <p className="truncate text-sm font-medium">{user?.full_name}</p>
            <p className="truncate text-xs text-muted">{user?.email}</p>
          </div>
          <ul className="py-1">
            <MenuLink to="/perfil" onClick={close} icon={<UserRound aria-hidden="true" className={iconClass} />}>
              Mi perfil
            </MenuLink>
            <MenuLink to="/perfil?tab=ia" onClick={close} icon={<Sparkles aria-hidden="true" className={iconClass} />}>
              Conexiones de IA
            </MenuLink>
            <MenuLink to="/seguridad" onClick={close} icon={<KeyRound aria-hidden="true" className={iconClass} />}>
              Seguridad
            </MenuLink>
            <li className="sm:hidden">
              <button type="button" onClick={onToggleTheme} className={itemClassName}>
                {theme === 'dark' ? <Sun aria-hidden="true" className={iconClass} /> : <Moon aria-hidden="true" className={iconClass} />}
                {theme === 'dark' ? 'Tema claro' : 'Tema oscuro'}
              </button>
            </li>
          </ul>
          <div className="border-t border-border py-1">
            <button type="button" onClick={() => logoutMutation.mutate()} className={`${itemClassName} text-danger`}>
              <LogOut aria-hidden="true" className="size-4" />
              Salir
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
