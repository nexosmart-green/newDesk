import { useEffect, useId, useRef, useState } from 'react'
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
        className="relative flex size-9 items-center justify-center rounded-full text-muted hover:text-text focus-visible:outline-2 focus-visible:outline-accent"
      >
        <svg aria-hidden="true" viewBox="0 0 24 24" className="size-5" fill="none" stroke="currentColor" strokeWidth="2">
          <path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
          <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
        </svg>
        {unread > 0 && (
          <span
            aria-hidden="true"
            className="absolute -right-0.5 -top-0.5 min-w-5 rounded-full bg-danger px-1 text-center text-xs font-semibold text-white"
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
          className="absolute inset-x-2 top-full z-50 mt-2 rounded-lg border border-border bg-surface shadow-lg sm:inset-x-auto sm:right-0 sm:w-80"
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
