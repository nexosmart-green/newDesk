import { api, dataOf, toApiError, type ApiEnvelope } from '@/lib/api'
import type { NotificationPage, NotificationPreference } from './types'

/** Not a standard paginated resource: `meta` carries `unread_count` for the bell. */
export async function fetchNotifications(): Promise<NotificationPage> {
  try {
    const response = await api.get<NotificationPage>('/notifications')
    return { data: response.data.data, meta: response.data.meta }
  } catch (error) {
    throw toApiError(error)
  }
}

export async function markNotificationRead(id: string): Promise<{ unread_count: number }> {
  return dataOf(api.post<ApiEnvelope<{ unread_count: number }>>(`/notifications/${encodeURIComponent(id)}/read`))
}

export async function markAllNotificationsRead(): Promise<{ unread_count: number }> {
  return dataOf(api.post<ApiEnvelope<{ unread_count: number }>>('/notifications/read-all'))
}

export async function fetchNotificationPreferences(): Promise<NotificationPreference[]> {
  return dataOf(api.get<ApiEnvelope<NotificationPreference[]>>('/me/notification-preferences'))
}

export async function updateNotificationPreferences(
  preferences: Pick<NotificationPreference, 'event' | 'email'>[],
): Promise<NotificationPreference[]> {
  return dataOf(api.put<ApiEnvelope<NotificationPreference[]>>('/me/notification-preferences', { preferences }))
}
