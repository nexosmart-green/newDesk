import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  fetchNotificationPreferences,
  fetchNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  updateNotificationPreferences,
} from './notificationsApi'
import type { NotificationPreference } from './types'

export const notificationsKey = ['notifications'] as const
const preferencesKey = ['notification-preferences'] as const

/** RF-09 bell: polled every minute and on focus. */
export function useNotifications() {
  return useQuery({
    queryKey: notificationsKey,
    queryFn: fetchNotifications,
    refetchInterval: 60_000,
    refetchOnWindowFocus: true,
  })
}

export function useMarkNotificationRead() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => markNotificationRead(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: notificationsKey }),
  })
}

export function useMarkAllNotificationsRead() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: markAllNotificationsRead,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: notificationsKey }),
  })
}

export function useNotificationPreferences() {
  return useQuery({ queryKey: preferencesKey, queryFn: fetchNotificationPreferences })
}

export function useUpdateNotificationPreferences() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (preferences: Pick<NotificationPreference, 'event' | 'email'>[]) =>
      updateNotificationPreferences(preferences),
    onSuccess: (preferences) => queryClient.setQueryData(preferencesKey, preferences),
    meta: { successMessage: 'Preferencias guardadas.' },
  })
}
