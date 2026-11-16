import { formatDateTime } from '@/lib/dates'
import type { AppNotification } from './types'

interface NotificationItemProps {
  notification: AppNotification
  onOpen: (notification: AppNotification) => void
}

/** Unread: weight, desk's pink dot ("new, now") and screen-reader text — never by color alone (RNF-13). */
export function NotificationItem({ notification, onOpen }: NotificationItemProps) {
  const unread = notification.read_at === null

  return (
    <li>
      <button
        type="button"
        onClick={() => onOpen(notification)}
        className="flex w-full gap-2.5 px-3 py-2.5 text-left hover:bg-text/5 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-accent"
      >
        <span aria-hidden="true" className={`mt-1.5 size-2 shrink-0 rounded-full ${unread ? 'bg-live' : ''}`} />
        <span className="min-w-0 space-y-0.5">
          <span className={`block text-sm ${unread ? 'font-medium' : 'text-muted'}`}>
            {unread && <span className="sr-only">Nueva: </span>}
            {notification.title}
          </span>
          {notification.body && <span className="block text-[13px] text-muted">{notification.body}</span>}
          {notification.created_at && (
            <span className="block text-xs text-muted">{formatDateTime(notification.created_at)}</span>
          )}
        </span>
      </button>
    </li>
  )
}
