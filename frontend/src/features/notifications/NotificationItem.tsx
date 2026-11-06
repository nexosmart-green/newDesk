import { formatDateTime } from '@/lib/dates'
import type { AppNotification } from './types'

interface NotificationItemProps {
  notification: AppNotification
  onOpen: (notification: AppNotification) => void
}

/** Unread is marked by weight, a dot and screen-reader text — never by color alone (RNF-13). */
export function NotificationItem({ notification, onOpen }: NotificationItemProps) {
  const unread = notification.read_at === null

  return (
    <li>
      <button
        type="button"
        onClick={() => onOpen(notification)}
        className="flex w-full gap-2 px-3 py-2 text-left hover:bg-bg focus-visible:outline-2 focus-visible:outline-accent"
      >
        <span aria-hidden="true" className={`mt-1.5 size-2 shrink-0 rounded-full ${unread ? 'bg-accent' : ''}`} />
        <span className="min-w-0 space-y-0.5">
          <span className={`block text-sm ${unread ? 'font-semibold' : ''}`}>
            {unread && <span className="sr-only">Nueva: </span>}
            {notification.title}
          </span>
          <span className="block text-xs text-muted">{notification.body}</span>
          {notification.created_at && (
            <span className="block text-xs text-muted">{formatDateTime(notification.created_at)}</span>
          )}
        </span>
      </button>
    </li>
  )
}
