import { Menu, Moon, Sun } from 'lucide-react'
import { DayClockButton } from '@/features/dayClock/DayClockButton'
import { NotificationBell } from '@/features/notifications/NotificationBell'
import { GlobalTimer } from '@/features/timer/GlobalTimer'
import { UserMenu } from './UserMenu'

interface HeaderProps {
  theme: 'dark' | 'light'
  onToggleTheme: () => void
  onOpenMenu: () => void
  /** Hidden when the role lacks the time module. */
  showTimer: boolean
}

const iconButton =
  'grid size-9 shrink-0 place-items-center rounded-md text-muted transition-colors hover:bg-surface-2 hover:text-text focus-visible:outline-2 focus-visible:outline-accent coarse:size-11'

/**
 * What is "live", not where you are (the page's own h1 says that): the day clock (RF-05.19) and the running
 * timer first (RF-05.01), then bell (RF-09), theme and account. Phone: menu · timer · bell · avatar.
 */
export function Header({ theme, onToggleTheme, onOpenMenu, showTimer }: HeaderProps) {
  return (
    <header className="sticky top-0 z-40 flex h-14 items-center gap-2 border-b border-line bg-surface px-3 sm:px-6">
      <button type="button" onClick={onOpenMenu} aria-label="Abrir menú" className={`${iconButton} lg:hidden`}>
        <Menu aria-hidden="true" className="size-5" />
      </button>
      <div className="flex min-w-0 flex-1 items-center gap-1 sm:gap-2">
        {showTimer && <DayClockButton />}
        {showTimer && <GlobalTimer />}
      </div>
      <div className="flex shrink-0 items-center gap-1">
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
