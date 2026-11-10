import { useEffect, useId, useRef, useState } from 'react'
import { Bell } from 'lucide-react'
import { useNavigate } from 'react-router'
import { Button } from '@/components/ui/Button'
import { NotificationItem } from './NotificationItem'
import type { AppNotification } from './types'
import { useMarkAllNotificationsRead, useMarkNotificationRead, useNotifications } from './useNotifications'

const badgeText = (count: number) => (count > 9 ? '9+' : String(count))

/** RF-09: header bell with unread count, list and "mark all as read". */
export function NotificationBell() {
  const panelId = useId()
  const navigate = useNavigate()
  const containerRef = useRef<HTMLDivElement>(null)
  const buttonRef = useRef<HTMLButtonElement>(null)
  const [open, setOpen] = useState(false)
  const notifications = useNotifications()
  const markRead = useMarkNotificationRead()
  const markAll = useMarkAllNotificationsRead()
  const unread = notifications.data?.meta.unread_count ?? 0
  const items = notifications.data?.data ?? []

  useEffect(() => {
    if (!open) return
    const onPointerDown = (event: PointerEvent) => {
      if (event.target instanceof Node && !containerRef.current?.contains(event.target)) setOpen(false)
    }
    document.addEventListener('pointerdown', onPointerDown)
    return () => document.removeEventListener('pointerdown', onPointerDown)
  }, [open])

  function close() {
    setOpen(false)
    buttonRef.current?.focus()
  }

  function openNotification(notification: AppNotification) {
    if (notification.read_at === null) markRead.mutate(notification.id)
    setOpen(false)
    navigate(notification.path)
  }

  return (
    <div
      ref={containerRef}
      className="sm:relative"
      onKeyDown={(event) => {
        if (event.key === 'Escape' && open) close()
      }}
    >
      <button
        ref={buttonRef}
        type="button"
        aria-label={`Notificaciones (${unread} sin leer)`}
        aria-expanded={open}
        aria-controls={panelId}
        onClick={() => setOpen((current) => !current)}
        className={`relative grid size-11 place-items-center rounded-lg transition-colors hover:bg-surface-2 hover:text-text focus-visible:outline-2 focus-visible:outline-accent ${open ? 'bg-surface-2 text-text' : 'text-muted'}`}
      >
        <Bell aria-hidden="true" className="size-5" strokeWidth={1.75} />
        {unread > 0 && (
          // "Live" pink from desk: something new is waiting.
          <span
            aria-hidden="true"
            className="tabular absolute right-1 top-1 min-w-5 rounded-full bg-live px-1 text-center text-[11px] font-bold leading-5 text-on-live ring-2 ring-surface"
          >
            {badgeText(unread)}
          </span>
        )}
      </button>
      {open && (
        <div
          id={panelId}
          role="region"
          aria-label="Notificaciones"
          className="absolute inset-x-2 top-full z-50 mt-2 overflow-hidden rounded-xl border border-border bg-surface shadow-xl sm:inset-x-auto sm:right-0 sm:w-96"
        >
          <div className="flex items-center justify-between gap-2 border-b border-border px-3 py-2">
            <h2 className="text-sm font-semibold">Notificaciones</h2>
            <Button variant="link" disabled={unread === 0 || markAll.isPending} onClick={() => markAll.mutate()}>
              Marcar todas como leídas
            </Button>
          </div>
          {items.length === 0 ? (
            <p className="px-3 py-4 text-sm text-muted">
              {notifications.isPending ? 'Cargando…' : 'No tenés notificaciones.'}
            </p>
          ) : (
            <ul className="max-h-96 divide-y divide-border overflow-y-auto">
              {items.map((notification) => (
                <NotificationItem key={notification.id} notification={notification} onOpen={openNotification} />
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  )
}
