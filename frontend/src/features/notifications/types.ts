export interface AppNotification {
  id: string
  event: string
  title: string
  body: string
  /** SPA path to open, e.g. `/tareas/bhn-60`. */
  path: string
  read_at: string | null
  created_at: string | null
}

export interface NotificationPage {
  data: AppNotification[]
  meta: { unread_count: number; current_page: number; last_page: number; total: number }
}

export interface NotificationPreference {
  event: string
  label: string
  email: boolean
  /** RF-09: hour-block e-mails can't be muted by an Admin. */
  locked: boolean
}
