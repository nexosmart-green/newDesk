import { useMutation, useMutationState, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  fetchNotificationPreferences,
  fetchNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  updateNotificationPreferences,
} from './notificationsApi'
import { notificationPreferencesKey, notificationsKey, preferenceToggleMutationKey } from '@/lib/queryKeys'
import type { NotificationPreference } from './types'

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
  return useQuery({ queryKey: notificationPreferencesKey, queryFn: fetchNotificationPreferences })
}

type EmailChange = Pick<NotificationPreference, 'event' | 'email'>

/**
 * Optimistic: the box ticks at once and goes back if the server refuses. The whole list is sent, built from
 * the cache after this change (so toggles in a row don't undo each other).
 */
export function useToggleEmailPreference() {
  const queryClient = useQueryClient()
  const read = () => queryClient.getQueryData<NotificationPreference[]>(notificationPreferencesKey) ?? []
  const write = ({ event, email }: EmailChange) =>
    queryClient.setQueryData<NotificationPreference[]>(notificationPreferencesKey, (list) =>
      list?.map((preference) => (preference.event === event ? { ...preference, email } : preference)),
    )
  return useMutation({
    mutationKey: preferenceToggleMutationKey,
    meta: { successMessage: 'Preferencias guardadas.' },
    onMutate: async (change: EmailChange) => {
      await queryClient.cancelQueries({ queryKey: notificationPreferencesKey })
      write(change)
    },
    mutationFn: () => updateNotificationPreferences(read().map(({ event, email }) => ({ event, email }))),
    onError: (_error, change) => write({ ...change, email: !change.email }),
    onSuccess: (preferences) => {
      // Another toggle still on its way already changed the cache: its own answer will settle it.
      if (queryClient.isMutating({ mutationKey: preferenceToggleMutationKey }) <= 1) queryClient.setQueryData(notificationPreferencesKey, preferences)
    },
  })
}

/** Events whose e-mail switch is being saved: only those boxes wait. */
export function useSavingPreferenceEvents(): string[] {
  return useMutationState({
    filters: { mutationKey: preferenceToggleMutationKey, status: 'pending' },
    select: (mutation) => (mutation.state.variables as EmailChange).event,
  })
}
