import { Menu, Moon, Sun } from 'lucide-react'
import { DayClockButton } from '@/features/dayClock/DayClockButton'
import { NotificationBell } from '@/features/notifications/NotificationBell'
import { GlobalTimer } from '@/features/timer/GlobalTimer'
import { UserMenu } from './UserMenu'

interface HeaderProps {
  title: string
  theme: 'dark' | 'light'
  onToggleTheme: () => void
  onOpenMenu: () => void
  /** Hidden when the role lacks the time module. */
  showTimer: boolean
}

const iconButton =
  'grid size-11 place-items-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-text focus-visible:outline-2 focus-visible:outline-accent'

/** Where the section is named and what is "live" sits: timer (RF-05.01), day clock (RF-05.19), bell (RF-09), theme, account. */
export function Header({ title, theme, onToggleTheme, onOpenMenu, showTimer }: HeaderProps) {
  return (
    <header className="sticky top-0 z-40 flex h-16 items-center gap-2 border-b border-border bg-surface/95 px-3 backdrop-blur-sm sm:px-6">
      <button type="button" onClick={onOpenMenu} aria-label="Abrir menú" className={`${iconButton} lg:hidden`}>
        <Menu aria-hidden="true" className="size-5" />
      </button>
      <p className="min-w-0 truncate text-base font-semibold tracking-tight sm:text-lg">{title}</p>
      <div className="ml-auto flex items-center gap-1 sm:gap-2">
        {showTimer && <DayClockButton />}
        {showTimer && <GlobalTimer />}
        <NotificationBell />
        <button
          type="button"
          onClick={onToggleTheme}
          aria-label={theme === 'dark' ? 'Usar tema claro' : 'Usar tema oscuro'}
          title={theme === 'dark' ? 'Tema claro' : 'Tema oscuro'}
          className={`${iconButton} hidden sm:grid`}
        >
          {theme === 'dark' ? <Sun aria-hidden="true" className="size-5" /> : <Moon aria-hidden="true" className="size-5" />}
        </button>
        <UserMenu theme={theme} onToggleTheme={onToggleTheme} />
      </div>
    </header>
  )
}
